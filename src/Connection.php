<?php

namespace o6web\PDO;

class Connection
{
	private string $name;
	private string $dsn;
	private string $username;
	private string $password;
	private array $options;
	private ?PDO $connection = null;

	public function __construct(string $name, string $dsn, string $username, string $password, array $options = [])
	{
		$this->name = $name;
		$this->dsn = $dsn;
		$this->username = $username;
		$this->password = $password;
		$this->options = $options;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getConnection(): PDO
	{
		if ($this->connection === null) {
			$this->connection = new PDO($this->dsn, $this->username, $this->password, $this->options);
		}

		return $this->connection;
	}

}