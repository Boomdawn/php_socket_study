<?php
/**
 * 多进程阻塞模型(能同时处理多少连接,取决于开了多少进程)
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
     * 开启子进程数
     * @var int
     */
    public $count = 2;

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
    }

    /**
     * 启动服务
     */
    public function start()
    {
        for ($i = 0; $i < $this->count; $i++) {
            $this->forkOne();
        }

        while(1) {
            //挂起主进程
            $status = 0;
            $pid = pcntl_wait($status, WUNTRACED);
            if ($pid > 0) {
                //如果有子进程退出,可以做一些事情。比如记录日志,重新生成一个子进程
            }
        }
    }

    /**
     * 开启子进程
     */
    protected function forkOne()
    {
        $pid = pcntl_fork();

        if ($pid === -1) {
            echo new Exception("fork fail");
        } elseif ($pid === 0) {
            while(1) {
                //等待连接
                $conn = stream_socket_accept($this->socket, -1);

                if ($this->onConnect) {
                    call_user_func($this->onConnect, $conn);
                }

                $this->recv($conn);
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
