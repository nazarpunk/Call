<?php /** @noinspection PhpUnused */
declare(strict_types=1);

namespace nazarpunk\Call;

use mysqli;
use mysqli_result;
use DateTime;
use Exception;

class Call {
	private mysqli        $mysqli;
	private string        $connection;
	private static string $connection_default;

	public function __construct(string $procedure) {
		$this->connection = static::$connection_default;
		$this->procedure  = $procedure;
	}

	//<editor-fold desc="connection">
	private static array $connections = [];

	public static function set_connection(
		string $name = 'main'
		, string $hostname = 'localhost'
		, string $username = 'root'
		, string $password = null
		, string $database = null
		, string $port = null
		, string $socket = null
		, string $charset = 'utf8mb4'
		, int $report = MYSQLI_REPORT_OFF
	) {
		self::$connection_default ??= $name;
		self::$connections[$name] = [
			// class
			'name'       => $name,
			'connection' => null,
			// connection
			'hostname'   => $hostname,
			'username'   => $username,
			'password'   => $password,
			'database'   => $database,
			'port'       => $port,
			'socket'     => $socket,
			// connection param
			'charset'    => $charset,
			'report'     => $report
		];
	}

	public static function use_connection(string $name) {
		self::$connection_default = $name;
	}

	/**
	 * @param string $name
	 * @return mysqli
	 * @throws Exception
	 */
	public static function get_connection(string $name): mysqli {
		if (!array_key_exists($name, static::$connections)) throw new Exception("Undefined connection: $name");
		if (static::$connections[$name]['connection'] instanceof mysqli) {
			mysqli_report(static::$connections[$name]['report']);
			return static::$connections[$name]['connection'];
		}

		$options = static::$connections[$name];
		$mysqli  = new mysqli($options['hostname'], $options['username'], $options['password'], $options['database'], $options['port'], $options['socket']);

		mysqli_report($options['report']);

		if ($mysqli->connect_errno) throw new Exception($mysqli->connect_error);
		if (!$mysqli->set_charset($options['charset'])) throw new Exception($mysqli->error);

		return static::$connections[$name]['connection'] = $mysqli;
	}

	/**
	 * @param string $name
	 * @return $this
	 */
	public function connection(string $name): Call {
		$this->connection = $name;
		return $this;
	}
	//</editor-fold>


	//<editor-fold desc="options">
	private static array $options = [
		'format'  => 'escape',
		'type'    => true,
		'null'    => true,
		'boolean' => true
	];

	/**
	 * @param string $format
	 * @param bool   $type
	 * @param bool   $null
	 * @param bool   $bool
	 * @throws Exception
	 */
	public static function set_options(
		string $format = 'escape'
		, bool $type = true
		, bool $null = true
		, bool $bool = true
	) {
		$options = array_replace_recursive(static::$options, [
			'format'    => $format
			, 'type'    => $type
			, 'null'    => $null
			, 'boolean' => $bool
		]);
		if (is_null($options)) throw new Exception('Wrong Call options');

		static::$options = $options;
	}
	//</editor-fold>

	//<editor-fold desc="procedure">
	private string $procedure;

	/**
	 * @param string $procedure
	 * @return $this
	 */
	public function procedure(string $procedure): Call {
		$this->procedure = $procedure;
		return $this;
	}
	//</editor-fold>

	//<editor-fold desc="variable">
	private array $variable = [];

	/**
	 * @param string      $name
	 * @param mixed       $value
	 * @param string|null $format
	 * @return $this
	 */
	public function variable(string $name, mixed $value, string $format = null): Call {
		$this->variable[$name] = func_get_args();
		return $this;
	}

	//</editor-fold>

	//<editor-fold desc="value">
	/**
	 * @param mixed  $value
	 * @param string $format
	 * @return string
	 */
	private function value(mixed $value, string $format): string {
		if (is_null($value)) return 'null';
		if (is_bool($value) || is_int($value)) return strval(intval($value));
		if (is_array($value)) return "'" . $this->mysqli->real_escape_string(json_encode($value)) . "'";
		switch ($format) {
			case 'raw':
				return $value;
			case 'quote':
				return "'" . $value . "'";
			case 'escape':
				return "'" . $this->mysqli->real_escape_string($value) . "'";
		}
		return $value;
	}
	//</editor-fold>

	//<editor-fold desc="argument">
	private array $argument = [];

	/**
	 * @param int         $index
	 * @param mixed       $value
	 * @param string|null $format
	 * @return $this
	 */
	public function argument(int $index, mixed $value, string $format = null): Call {
		$this->argument[$index] = func_get_args();
		return $this;
	}
	//</editor-fold>

	//<editor-fold desc="result">
	/**
	 * @param mysqli_result $result
	 * @param array         $options
	 * @return array
	 * @throws Exception
	 */
	private static function results(mysqli_result $result, array $options): array {
		$fetch_fields = [];
		while ($fetch_field = $result->fetch_field()) {
			$fetch_fields[$fetch_field->name] = $fetch_field;
		}

		$results = [];
		while ($row = $result->fetch_assoc()) {
			foreach ($row as $key => &$value) {
				if (!array_key_exists($key, $fetch_fields)) continue;
				if (!$options['type']) {
					if (!$options['null'] && is_null($value)) $value = '';
					continue;
				}
				if ($options['null'] && is_null($value)) continue;

				$field = $fetch_fields[$key];

				if (MYSQLI_NUM_FLAG & $field->flags) {
					switch ($field->type) {
						case MYSQLI_TYPE_SHORT:
						case MYSQLI_TYPE_LONG:
						case MYSQLI_TYPE_INT24:
						case MYSQLI_TYPE_YEAR:
							settype($value, 'int');
							break;
						case MYSQLI_TYPE_LONGLONG:
						case MYSQLI_TYPE_TINY:
							settype($value, $options['boolean'] && $field->length === 1 ? 'bool' : 'int');
					}
				} else {
					switch ($field->type) {
						case MYSQLI_TYPE_JSON:
							$value = $value ? json_decode($value, JSON_OBJECT_AS_ARRAY) : [];
							break;
						case MYSQLI_TYPE_FLOAT:
						case MYSQLI_TYPE_DOUBLE:
						case MYSQLI_TYPE_DECIMAL:
						case MYSQLI_TYPE_NEWDECIMAL:
							settype($value, 'float');
							break;
						case MYSQLI_TYPE_TINY_BLOB:
						case MYSQLI_TYPE_MEDIUM_BLOB:
						case MYSQLI_TYPE_LONG_BLOB:
						case MYSQLI_TYPE_BLOB:
						case MYSQLI_TYPE_VAR_STRING:
						case MYSQLI_TYPE_CHAR:
						case MYSQLI_TYPE_STRING:
						case MYSQLI_TYPE_BIT:
						case MYSQLI_TYPE_ENUM:
						case MYSQLI_TYPE_SET:
							settype($value, 'string');
							break;
						case MYSQLI_TYPE_INTERVAL:
						case MYSQLI_TYPE_GEOMETRY:
						case MYSQLI_TYPE_TIME:
						case MYSQLI_TYPE_DATE:
						case MYSQLI_TYPE_NEWDATE:
						case MYSQLI_TYPE_NULL:
							break;
						case MYSQLI_TYPE_TIMESTAMP:
						case MYSQLI_TYPE_DATETIME:
							$value = new DateTime($value);
							break;
					}
				}
			}
			unset($value);
			$results[] = $row;
		}
		$result->free();
		return $results;
	}
	//</editor-fold>

	//<editor-fold desc="execute">
	/**
	 * @param string $format
	 * @param bool   $type
	 * @param bool   $null
	 * @param bool   $bool
	 * @return array
	 * @throws Exception
	 */
	public function execute(
		string $format = 'escape'
		, bool $type = true
		, bool $null = true
		, bool $bool = true
	): array {
		$this->mysqli = static::get_connection($this->connection);

		// options
		$options = array_replace_recursive(static::$options, [
				'format'    => $format
				, 'type'    => $type
				, 'null'    => $null
				, 'boolean' => $bool
			]);
		if (is_null($options)) throw new Exception('Wrong Call Argument');

		// variable
		$variable = [];
		if (count($this->variable)) {
			foreach ($this->variable as $k => $v) {
				$variable[] = "set @$k := " . static::value($v[1], $v[2] ?? $options['format']) . ';';
			}
		}

		// argument
		$argument = [];
		if (count($this->argument)) {
			for ($i = max(array_keys($this->argument)); $i >= 1; $i--) {
				if (!array_key_exists($i, $this->argument)) $this->argument[$i] = [$i, 'null', 'raw'];
			}
			ksort($this->argument, SORT_NUMERIC);
			foreach ($this->argument as $k => $v) {
				$argument[] = static::value($v[1], $v[2] ?? $options['format']);
			}
		}

		// result
		$query = implode('', $variable) . "call `{$this->procedure}` (" . implode(',', $argument) . ');';

		$this->mysqli->multi_query($query);
		$results = [];
		do {
			if ($result = $this->mysqli->store_result()) $results[] = static::results($result, $options);
			elseif ($this->mysqli->errno) throw new Exception("({$this->mysqli->errno}) {$this->mysqli->error}");
		} while ($this->mysqli->more_results() && $this->mysqli->next_result());

		return $results;
	}
	//</editor-fold>

}