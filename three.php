<?php
/**
 * 单进程IO复用模型select(能同时处理N个连接,可以自行压测)
 */
class Server
{
    /**
     * 监听的socket
     * @var resource
     */
    private $socket;
    /**
     * 连接时回调函数
     * @var callable
     */
    public $onConnect = null;
    /**
     * 客户端发来消息时回调函数
     * @var callable
     */
    public $onMessage = null;
    /**
     * 关闭连接时回调函数
     * @var callable
     */
    public $onClose = null;
    /**
     * 保存所有连接
     * @var array
     */
    protected $conns = [];
    /**
     * 当数据可读时，从socket缓冲区读取多少字节数据
     */
    const READ_BUFFER_SIZE = 65535;
    /**
     * Server constructor.
     * @param string $host
     * @param string $port
     */
    public function __construct($host = "0.0.0.0", $port = "1993")
    {
        //监听socket
        $this->socket = stream_socket_server("tcp://{$host}:{$port}",$errno, $errstr);
        if (!$this->socket) {
            echo new Exception($errstr." -- ".$errno);
        }
        //设置非阻塞
        stream_set_blocking($this->socket, 0);
        //保存当前socket
        $this->conns[(int)$this->socket] = $this->socket;
    }
    /**
     * 启动服务
     */
    public function start()
    {
        while(1) {
            $read = $this->conns;
            $mod_fd = @stream_select($read, $write = null, $except = null, 0, 1000000000);
            if ($mod_fd === false) {
                break;
            }
            foreach ($read as $key => $conn) {
                if ($conn == $this->socket) {
                    //新连接
                    $new_conn = stream_socket_accept($this->socket);
                    if($this->onConnect) {
                        call_user_func($this->onConnect, $new_conn);
                    }
                    $this->conns[(int)$new_conn] = $new_conn;
                } else {
                    //读取数据
                    stream_set_blocking($conn, 0);
                    $this->recv($conn);
                }
            }
        }
    }
    /**
     * 接收数据
     * @param $conn
     */
    protected function recv($conn)
    {
        //保存消息
        $recvData = [];
        $id = (int)$conn;
        $buffer = fread($conn, self::READ_BUFFER_SIZE);
        //判断连接是否关闭
        if (feof($conn) || !is_resource($conn) || $buffer === false) {
            if ($this->onClose){
                call_user_func($this->onClose);
            }
            unset($recvData[$id]);
            unset($this->conns[$id]);
        }
        //组装数据
        if (!isset($recvData[$id])) {
            $recvData[$id] = "";
        }
        $recvData[$id] .= $buffer;
        //TODO 自定义协议
        if ($this->onMessage){
            call_user_func($this->onMessage, $conn, $recvData[$id]);
        }
        $recvData[$id] = '';
    }
}
$server = new Server();
//$server->onConnect = function($conn) {
//    echo "new conn\n";
//    fwrite($conn, "welcome!\n");
//};
$server->onMessage = function($conn, $data) {
    //fwrite($conn, "hello {$data}");
    fwrite($conn,"HTTP/1.1 200 OK\r\nConnection: keep-alive\r\nServer: Dawn\r\n\r\nhello");
};
//$server->onClose = function() {
//    echo "conn closed\n";
//};
$server->start();
