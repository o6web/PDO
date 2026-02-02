<?php

namespace o6web\PDO;

use Exception;
use Monolog\Logger;

class Connection
{
	private string $name;
	private string $dsn;
	private string $username;
	private string $password;
	private array $options;
	private ?Logger $logger;
	private bool $isWebRequest;

	private ?PDO $connection = null;

	public function __construct(string $name, string $dsn, string $username, string $password, array $options = [], ?Logger $logger = null, bool $isWebRequest = false)
	{
		$this->name = $name;
		$this->dsn = $dsn;
		$this->username = $username;
		$this->password = $password;
		$this->options = $options;
		$this->logger = $logger;
		$this->isWebRequest = $isWebRequest;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getConnection(): PDO
	{
		if ($this->connection === null) {
			try {
				$this->connection = new PDO($this->dsn, $this->username, $this->password, $this->options);
				if ($this->logger) {
					$this->connection->setLogger($this->logger);
				}
			} catch (Exception $e) {
				$msg = 'Database connection error.';
				if ($this->logger) {
					$this->logger->alert($msg, ['exception' => $e]);
				}
				if ($this->isWebRequest) {
					echo $msg;
				}

				exit;
			}
		}

		return $this->connection;
	}

}