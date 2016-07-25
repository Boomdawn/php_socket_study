<?php
/**
 * 单进程阻塞模型(只能同时处理一个连接)
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
            die($errstr." -- ".$errno);
        }
    }

    /**
     * 启动服务
     */
    public function start()
    {
        while(1) {
            //等待连接
            $conn = stream_socket_accept($this->socket, -1);

            if ($this->onConnect) {
                call_user_func($this->onConnect, $conn);
            }

            $this->recv($conn);
        }
    }

    /**
     * 接收数据
     * @param $conn
     */
    protected function recv($conn)
    {
        //保存消息
        $recvData = '';

        while(1) {
            $buffer = fread($conn, self::READ_BUFFER_SIZE);
            //判断连接是否关闭
            if (feof($conn) || !is_resource($conn) || $buffer === false) {
                if ($this->onClose){
                    call_user_func($this->onClose);
                }
                break;
            }

            //组装数据
            $recvData .= $buffer;
            //TODO 自定义协议

            if ($this->onMessage) {
                call_user_func($this->onMessage, $conn, $recvData);
                $recvData = '';
            }
        }
    }
}

$server = new Server();

$server->onConnect = function($conn) {
    echo "new conn\n";
    fwrite($conn, "welcome!\n");
};

$server->onMessage = function($conn, $data) {
    fwrite($conn, "hello {$data}");
};

$server->onClose = function() {
    echo "conn closed\n";
};

$server->start();
