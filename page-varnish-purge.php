<?php
/* Template Name: varnish-purge */

require_once 'page-config.php';

$param = get_param();
if (@$param['api'] !== API_KEY) {die();}

$cmdlist = get_input_cmdlist();

foreach($varnish_secrets as $item) {
	exec_command_host($item['host'], $item['port'], $item['secret'], $cmdlist);
}

function exec_command_host($host, $port, $secret, $cmdlist) 
{
	echo "<hr><h3>清除服务器：{$host}</h3><hr>";
	$Sock = new VarnishAdminSocket($host, $port, '3.0.1' );
	$Sock->set_auth($secret);

	echo "连接varnish服务器 {$host}:{$port} ... <br>";
	try {
	    //连接服务器，5秒超时
	    $Sock->connect(5);
	    echo "连接成功<br>";
	}
	catch( Exception $Ex ){
	    echo '**连接失败**: ', $Ex->getMessage(), "<br>";
	    exit(0);
	}

	//检查服务器状态
	$running = $Sock->status();
	echo '服务器运行状态: ', $running ? '运行中' : '有问题', "<p>";

	//清除主页
	foreach($cmdlist as $cmdstr) {
		echo "执行命令：{$cmdstr}<br>";
		$exec_res = $Sock->command($cmdstr, $code);
		echo "执行结果：({$code}) $exec_res<br>";
	}

	//显具体的清除列表
	echo "<p>历史清除列表...<br>";
	$list = $Sock->purge_list();
	var_dump( $list );
	echo "<p>";

	$Sock->quit();
}

function get_input_cmdlist()
{
	$this_page = get_page(get_the_id());
	$content = $this_page->post_content;
	$items = explode("\r\n", $content);
	return $items;
}


function get_param($key = null)
{
	$union = array_merge($_GET, $_POST); 
	if ($key) {
		return @$union[$key];
	} else {
		return $union;
	}
}

/**
 * Varnish admin socket for executing varnishadm CLI commands.
 * @see http://varnish-cache.org/wiki/CLI
 * @author Tim Whitlock http://twitter.com/timwhitlock
 * 
 * CLI commands available as follows:
    help [command]
    ping [timestamp]
    auth response
    quit
    banner
    status
    start
    stop
    stats
    vcl.load <configname> <filename>
    vcl.inline <configname> <quoted_VCLstring>
    vcl.use <configname>
    vcl.discard <configname>
    vcl.list
    vcl.show <configname>
    param.show [-l] [<param>]
    param.set <param> <value>
    purge.url <regexp>
    purge <field> <operator> <arg> [&& <field> <oper> <arg>]...
    purge.list
 */

class VarnishAdminSocket {
    private $fp;
    private $host;
    private $port;
    private $secret;
    private $version;
    private $version_minor;
    private $ban = '';

    public function __construct( $host = '127.0.0.1', $port = 6082, $v = '2.1' ){
        $this->host = $host;
        $this->port = $port;
        // parse expected version number
        $vers = explode('.',$v,3);
        $this->version = isset($vers[0]) ? (int) $vers[0] : 2;
        $this->version_minor = isset($vers[1]) ? (int) $vers[1] : 1;
        if( 2 === $this->version ){
            // @todo sanity check 2.x number
        }
        else if( 3 === $this->version ){
            // @todo sanity check 3.x number
        }
        else {
            throw new Exception('Only versions 2 and 3 of Varnish are supported');
        }
        $this->ban = $this->version === 3 ? 'ban' : 'purge';
    }

    public function set_auth( $secret ){
        $this->secret = $secret;
    }

    public function connect( $timeout = 5 ){
        $this->fp = fsockopen( $this->host, $this->port, $errno, $errstr, $timeout );
        if( ! is_resource( $this->fp ) ){
            // error would have been raised already by fsockopen
            throw new Exception( sprintf('Failed to connect to varnishadm on %s:%s; "%s"', $this->host, $this->port, $errstr ));
        }
        // set socket options
        stream_set_blocking( $this->fp, 1 );
        stream_set_timeout( $this->fp, $timeout );
        // connecting should give us the varnishadm banner with a 200 code, or 107 for auth challenge
        $banner = $this->read( $code );
        if( $code === 107 ){
            if( ! $this->secret ){
                throw new Exception('Authentication required; see VarnishAdminSocket::set_auth');
            }
            try {
                $challenge = substr( $banner, 0, 32 );
                $response = hash('sha256', $challenge."\n".$this->secret.$challenge."\n");
                $banner = $this->command('auth '.$response, $code, 200 );
            }
            catch( Exception $Ex ){
                throw new Exception('Authentication failed');
            }
        }
        if( $code !== 200 ){
            throw new Exception( sprintf('Bad response from varnishadm on %s:%s', $this->host, $this->port));
        }
        return $banner;
    }
    
    private function write( $data ){
        $bytes = fputs( $this->fp, $data );
        if( $bytes !== strlen($data) ){
            throw new Exception( sprintf('Failed to write to varnishadm on %s:%s', $this->host, $this->port) );
        }
        return true;
    }    
    
    public function command( $cmd, &$code, $ok = 200 ){
        $cmd and $this->write( $cmd );
        $this->write("\n");
        $response = $this->read( $code );
        if( $code !== $ok ){
            $response = implode("\n > ", explode("\n",trim($response) ) );
            throw new Exception( sprintf("%s command responded %d:\n > %s", $cmd, $code, $response), $code );
        }
        return $response;
    }
    
    private function read( &$code ){
        $code = null;
        // get bytes until we have either a response code and message length or an end of file
        // code should be on first line, so we should get it in one chunk
        while ( ! feof($this->fp) ) {
            $response = fgets( $this->fp, 1024 );
            if( ! $response ){
                $meta = stream_get_meta_data($this->fp);
                if( $meta['timed_out'] ){
                    throw new Exception(sprintf('Timed out reading from socket %s:%s',$this->host,$this->port));
                }
            }
            if( preg_match('/^(\d{3}) (\d+)/', $response, $r) ){
                $code = (int) $r[1];
                $len = (int) $r[2];
                break;
            }
        }
        if( is_null($code) ){
            throw new Exception('Failed to get numeric code in response');
        }
        $response = '';
        while ( ! feof($this->fp) && strlen($response) < $len ) {
            $response .= fgets( $this->fp, 1024 );
        }
        return $response;
    }
    
    public function close(){
        is_resource($this->fp) and fclose($this->fp);
        $this->fp = null;
    }
    
    public function quit(){
        try {
            $this->command('quit', $code, 500 );
        }
        catch( Exception $Ex ){
            // slient fail - force close of socket
        }
        $this->close();
    }

    public function purge( $expr ){
        return $this->command( $this->ban.' '.$expr, $code );
    }
    
    public function purge_url( $expr ){
        return $this->command( $this->ban.'.url '.$expr, $code );
    }    

    public function purge_list(){
        $response = $this->command( $this->ban.'.list', $code );
        return explode( "\n", trim($response) );
    }
    
    public function status(){
        try {
            $response = $this->command( 'status', $code );
            if( ! preg_match('/Child in state (\w+)/', $response, $r ) ) {
                return false;
            }
            return $r[1] === 'running';
        }
        catch( Exception $Ex ){
            return false;
        }
    }
    
    public function stop(){
        if( ! $this->status() ){
            trigger_error(sprintf('varnish host already stopped on %s:%s', $this->host, $this->port), E_USER_NOTICE);
            return true;
        }
        $this->command( 'stop', $code );
        return true;
    }    
    
    public function start(){
        if( $this->status() ){
            trigger_error(sprintf('varnish host already started on %s:%s', $this->host, $this->port), E_USER_NOTICE);
            return true;
        }
        $this->command( 'start', $code );
        return true;
    }
}

?>
