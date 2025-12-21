<?php

namespace o6web\PDO;

use Exception;
use Monolog\Logger;

class PDOStatement extends \PDOStatement
{
	protected PDO $pdo;
	private array $_inputParameters = [];
	private array $_expectedParameters = [];
	private bool $executeResponse;
	private bool $isFetchFriendly;

	/**
	 * @param PDO $pdo
	 */
	protected function __construct(PDO $pdo)
	{
		$this->pdo = $pdo;
		$this->isFetchFriendly = ((stripos(trim($this->queryString), 'SELECT') === 0) OR (stripos(trim($this->queryString), 'SHOW') === 0));
	}

	/**
	 * Set the expected parameters
	 *
	 * @param array $expectedParameters
	 */
	public function setExpectedParameters(array $expectedParameters): void
	{
		$this->_expectedParameters = $expectedParameters;
	}

	/**
	 * Return success or failure of the most recent execution of the statement
	 *
	 * @return bool
	 */
	public function getExecuteResponse(): bool
	{
		return $this->executeResponse;
	}

	/**
	 * Bind a value to the statement, stripping slashes and forcing NULLs for strings
	 *
	 * @param int|string $param
	 * @param int|string|null|array $value
	 * @param int $type
	 * @param int $null_opt
	 * @param ?int $length
	 * @return bool
	 */
	public function bindValue(
		$param,
		$value,
		$type = PDO::PARAM_STR,
		int $null_opt = PDO::NULL_OPT_NONE,
		?int $length = null
	): ?bool {
		if ((is_array($value)) && (count($value) === 1)) {
			$value = $value[0];
		}
		// IF WE'RE SENDING IN AN ARRAY, LOOP THROUGH THEM
		if (is_array($value)) {
			$n = 1;
			do {
				foreach ($value AS $val) {
					if (is_numeric($param)) {
						$field = $param + $n - 1;
					} else {
						$field = $param . $n;
					}

					$this->bindValue($field, $val, $type, $null_opt, $length);
					$n++;
				}
			} while ($n <= $this->_expectedParameters[$param]);

			return $n;
		}

		// IF WE EXPECT MORE THAN ONE, LOOP THROUGH TO ASSIGN THE VALUE EVERYWHERE NEEDED
		if ((array_key_exists($param, $this->_expectedParameters)) && ($this->_expectedParameters[$param] > 1)) {
			$result = false;
			for ($n = 1; $n <= $this->_expectedParameters[$param]; $n++) {
				$result = $this->bindValue(($param . $n), $value, $type, $null_opt, $length);
			}

			return $result;
		}

		if ($type === PDO::PARAM_INT) {
			if (((int)$value === 0) && ($null_opt === PDO::NULL_OPT_FORCE)) {
				$value = null;
				$type = PDO::PARAM_NULL;
			} elseif (($value === '') || (($value === null) && ($null_opt === PDO::NULL_OPT_DISALLOW))) {
				$value = 0;
			} elseif ($value === null) {
				$type = PDO::PARAM_NULL;
			} else {
				$value = (int)$value;
			}
		} elseif ($type === PDO::PARAM_STR) {
			if (($null_opt === PDO::NULL_OPT_FORCE) && ($value === '')) {
				$value = null;
			} elseif (($null_opt === PDO::NULL_OPT_DISALLOW) && ($value === null)) {
				$value = '';
			} elseif (($length) && ($value !== null)) {
				$value = substr($value, 0, $length);
			}

			if ($value === null) {
				$type = PDO::PARAM_NULL;
			} else {
				// WORDPRESS REQUIRES THIS
				$value = stripslashes($value);
			}
		}

		$this->_inputParameters[$param] = ['value' => $value, 'type' => $type];

		try {
			return parent::bindValue($param, $value, $type);
		} catch (Exception $e) {
			$this->pdo->hasError = 1;

			if ($this->pdo->logger) {
				$this->pdo->logger->critical('Value could not be bound', [
					'parameter' => $param,
					'value' => $value,
					'type' => $type,
					'exception' => $e,
					'debug' => $this->debugDumpParams(),
				]);
			}

			return false;
		}
	}

	/**
	 * Execute an o6PDOStatement and log any errors, returning the o6PDOStatement
	 *
	 * @param null $params
	 * @return self|bool
	 */
	public function execute($params = null)
	{
		try {
			$this->executeResponse = parent::execute($params);
		} catch (Exception $e) {
			$this->pdo->hasError = 1;

			if ($this->pdo->logger) {
				$this->pdo->logger->critical('Statement could not be executed', [
					'exception' => $e,
					'debug' => $this->debugDumpParams(),
					'input' => $this->_inputParameters,
				]);
			}

			return false;
		}

		if ((!$this->executeResponse) && ($this->pdo->logger)) {
			$this->pdo->logger->critical('Prepared statement could not be executed', [
				'error' => $this->errorInfo(),
				'debug' => $this->debugDumpParams(),
				'input' => $this->_inputParameters,
			]);
		}

		return $this;
	}

	/**
	 * Fetch the next result from the statement
	 *
	 * @param null $mode
	 * @param int $cursorOrientation
	 * @param int $cursorOffset
	 * @return mixed
	 */
	public function fetch($mode = null, $cursorOrientation = \PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
	{
		if ((!$this->isFetchFriendly) && ($this->pdo->logger)) {
			$this->pdo->logger->error('Attempted fetch on non-select/show query', [
				'query' => $this->queryString,
			]);

		}

		return parent::fetch($mode, $cursorOrientation, $cursorOffset);
	}

	/**
	 * Return the value of the first record of a specified column
	 *
	 * @param string|int $key
	 * @return mixed
	 */
	public function fetchColumnValue($key = null)
	{
		$record = $this->fetch();
		if (is_array($record)) {
			if ($key === null) {
				return reset($record);
			}

			return $record[$key] ?? null;
		}

		return null;
	}

	/**
	 * Return all rows of a single column as an array
	 *
	 * @param string|int $key
	 * @param ?array $output
	 * @return array
	 */
	public function fetchColumnAll($key = null, ?array $output = null): array
	{
		if (!is_array($output)) {
			$output = [];
		}

		while ($record = $this->fetch(PDO::FETCH_ASSOC)) {
			if ($key === null) {
				$output[] = reset($record);
			} else {
				$output[] = $record[$key];
			}
		}
		return $output;
	}

	/**
	 * Return an array of all rows with the value of the specified field(s) as key(s)
	 *
	 * @param string|integer|array $key_field
	 * @param bool $remove_key
	 * @param bool $return_array
	 * @param string|integer $value_field
	 * @return array
	 */
	public function fetchAllKeyed($key_field, bool $remove_key = false, bool $return_array = true, $value_field = ''): array
	{
		if (!is_array($key_field)) {
			$key_field = [$key_field];
		}

		$build_output = static function (&$out, $keys, $value, $array_out, $val_field) use (&$build_output) {
			if (count($keys)) {
				$key = array_shift($keys);

				if (!is_array($out[$key])) {
					$out[$key] = [];
				}

				$build_output($out[$key], $keys, $value, $array_out, $val_field);
			} else {
				if ($val_field) {
					// WE'VE SPECIFIED A FIELD TO USE AS VALUE
					$value = $value[$val_field];
				}

				if ($array_out) {
					// WE'VE SPECIFIED TO APPEND NEW VALUES TO AN ARRAY
					$out[] = $value;
				} else {
					$out = $value;
				}
			}
		};

		$output = [];
		while ($record = $this->fetch(PDO::FETCH_ASSOC)) {
			$keys = [];

			foreach ($key_field AS $key) {
				$keys[] = $record[$key];

				if ($remove_key) {
					unset($record[$key]);
				}
			}

			$build_output($output, $keys, $record, $return_array, $value_field);
		}

		return $output;
	}

	/**
	 * Override the debugDumpParams methos to return a string
	 *
	 * @return string
	 */
	public function debugDumpParams(): string
	{
		ob_start();
		parent::debugDumpParams();
		return ob_get_clean();
	}

	public function debug($exit = false): void
	{
		$debug = [
			'_inputParameters' => $this->_inputParameters,
			'_expectedParameters' => $this->_expectedParameters,
			'queryString' => $this->queryString,
		];
		var_dump($debug);

		if ($exit) {
			exit;
		}
	}
}
