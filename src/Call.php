<?php /** @noinspection PhpUnused */

namespace nazarpunk\Call;

use mysqli;
use mysqli_result;
use DateTime;
use Exception;

class Call {
	private mysqli $mysqli;

	public function __construct(string $procedure) {
		$this->connection = static::$connection_default;
		$this->procedure  = $procedure;
	}

	//<editor-fold desc="connection">
	private static array $connections = [];

	public static function set_connection(array $options) {
		$options = array_replace_recursive(
			[
				'name'     => 'main',
				'hostname' => null,
				'username' => null,
				'password' => null,
				'database' => null,
				'port'     => null,
				'socket'   => null,
				'charset'  => 'utf8mb4',
				'report'   => MYSQLI_REPORT_OFF
			], $options);

		$options['connection'] = null;

		self::$connection_default            ??= $options['name'];
		self::$connections[$options['name']] = $options;
	}

	private string        $connection;
	private static string $connection_default;

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
		if (static::$connections[$name]['connection'] instanceof mysqli) return static::$connections[$name]['connection'];

		$options = static::$connections[$name];
		$mysqli  = new mysqli($options['hostname'], $options['username'], $options['password'], $options['database'], $options['port'], $options['socket']);

		if ($mysqli->connect_errno) throw new Exception($mysqli->connect_error);
		if (!$mysqli->set_charset('utf8mb4')) throw new Exception($mysqli->error);

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

	public static function set_options(array $options) {
		static::$options = array_replace_recursive(static::$options, $options) ?? static::$options;
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
	 * @param string $name
	 * @param mixed $value
	 * @param string|null $format
	 * @return $this
	 */
	public function variable(string $name, $value, string $format = null): Call {
		$this->variable[$name] = func_get_args();
		return $this;
	}

	//</editor-fold>

	//<editor-fold desc="value">
	/**
	 * @param mixed $value
	 * @param string $format
	 * @return string
	 */
	private function value($value, string $format): string {
		if (is_null($value)) return 'null';
		if (is_bool($value) || is_int($value)) return (int)$value;
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
	 * @param int $index
	 * @param mixed $value
	 * @param string|null $format
	 * @return $this
	 */
	public function argument(int $index, $value, string $format = null): Call {
		$this->argument[$index] = func_get_args();
		return $this;
	}

	//</editor-fold>
	//<editor-fold desc="result">
	public static function type($type_id) {
		static $types;

		if (!isset($types)) {
			$types     = [];
			$constants = get_defined_constants(true);
			foreach ($constants['mysqli'] as $c => $n) if (preg_match('/^MYSQLI_TYPE_(.*)/', $c, $m)) $types[$n] = $m[1];
		}

		return array_key_exists($type_id, $types) ? $types[$type_id] : null;
	}

	/** @noinspection PhpUnusedPrivateMethodInspection */
	private static function flags($flags_num): string {
		static $flags;

		if (!isset($flags)) {
			$flags     = [];
			$constants = get_defined_constants(true);
			foreach ($constants['mysqli'] as $c => $n) if (preg_match('/MYSQLI_(.*)_FLAG$/', $c, $m)) if (!array_key_exists($n, $flags)) $flags[$n] = $m[1];
		}

		$result = [];
		foreach ($flags as $n => $t) if ($flags_num & $n) $result[] = $t;
		return implode(' ', $result);
	}

	/**
	 * @param mysqli_result $result
	 * @param array $options
	 * @return array
	 * @throws Exception
	 */
	private static function results(mysqli_result $result, array $options): array {
		$fetch_fields = [];
		while ($fetch_feild = $result->fetch_field()) {
			$fetch_fields[$fetch_feild->name] = $fetch_feild;
		}

		$results = [];
		while ($row = $result->fetch_assoc()) {
			foreach ($row as $key => &$value) {
				if (!array_key_exists($key, $fetch_fields)) continue;
				if (!$options['type']) {
					if ($options['null'] && is_null($value)) $value = '';
					continue;
				}

				$field = $fetch_fields[$key];

				switch ($field->type) {
					/** @noinspection PhpMissingBreakStatementInspection */
					case MYSQLI_TYPE_TINY:
						if ($options['boolean'] && $field->length === 1) {
							if ($options['null'] && is_null($value)) break;
							settype($value, 'bool');
							break;
						}
					case MYSQLI_TYPE_SHORT:
					case MYSQLI_TYPE_LONG:
					case MYSQLI_TYPE_LONGLONG:
					case MYSQLI_TYPE_INT24:
					case MYSQLI_TYPE_YEAR:
						if ($options['null'] && is_null($value)) break;
						settype($value, 'int');
						break;
					case MYSQLI_TYPE_JSON:
						$value = $value ? json_decode($value, JSON_OBJECT_AS_ARRAY) : [];
						break;
					case MYSQLI_TYPE_FLOAT:
					case MYSQLI_TYPE_DOUBLE:
					case MYSQLI_TYPE_DECIMAL:
					case MYSQLI_TYPE_NEWDECIMAL:
						if ($options['null'] && is_null($value)) break;
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
						if ($options['null'] && is_null($value)) break;
						settype($value, 'string');
						break;
					case MYSQLI_TYPE_INTERVAL:
					case MYSQLI_TYPE_GEOMETRY:
					case MYSQLI_TYPE_TIME:
					case MYSQLI_TYPE_DATE:
					case MYSQLI_TYPE_NEWDATE:
						break;
					case MYSQLI_TYPE_TIMESTAMP:
					case MYSQLI_TYPE_DATETIME:
						if ($options['null'] && is_null($value)) break;
						$value = new DateTime($value);
						break;
					case MYSQLI_TYPE_NULL:
						throw new Exception('Null Finded');
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
	 * @param array $options
	 * @return array
	 * @throws Exception
	 */
	public function execute(array $options = []): array {
		$this->mysqli = static::get_connection($this->connection);

		// options
		$options = array_replace_recursive(static::$options, $options);

		// variable
		$variable = [];
		if (count($this->variable) > 0) {
			foreach ($this->variable as $k => $v) {
				$variable[] = "set @$k := " . static::value($v[1], $v[2] ?? $options['format']) . ";";
			}
		}

		// argument
		$argument = [];
		if (count($this->argument) > 0) {
			for ($i = max(array_keys($this->argument)); $i >= 1; $i--) {
				if (!array_key_exists($i, $this->argument)) $this->argument[$i] = [$i, 'null', 'raw'];
			}
			ksort($this->argument, SORT_NUMERIC);
			foreach ($this->argument as $k => $v) {
				$argument[] = static::value($v[1], $v[2] ?? $options['format']);
			}
		}

		// result
		$query = implode('', $variable) . "call `{$this->procedure}` (" . implode(',', $argument) . ");";

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