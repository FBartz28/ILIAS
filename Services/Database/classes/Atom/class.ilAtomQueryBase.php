<?php
require_once('./Services/Database/exceptions/exception.ilDatabaseException.php');
require_once('./Services/Database/interfaces/interface.ilAtomQuery.php');

/**
 * Class ilAtomQuery
 *
 * Use ilAtomQuery to fire Database-Actions which have to be done without beeing influenced by other queries or which can influence other queries as
 * well. Depending on the current Database-engine, this can be done by using transaction or with table-locks
 *
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
abstract class ilAtomQueryBase implements ilAtomQuery {

	const ITERATIONS = 10;
	/**
	 * @var array
	 */
	protected static $available_isolations_levels = array(
		ilAtomQuery::ISOLATION_READ_UNCOMMITED,
		ilAtomQuery::ISOLATION_READ_COMMITED,
		ilAtomQuery::ISOLATION_REPEATED_READ,
		ilAtomQuery::ISOLATION_SERIALIZABLE,
	);
	/**
	 * @var array
	 */
	protected static $possible_anomalies = array(
		ilAtomQuery::ANO_LOST_UPDATES,
		ilAtomQuery::ANO_DIRTY_READ,
		ilAtomQuery::ANO_NON_REPEATED_READ,
		ilAtomQuery::ANO_PHANTOM,
	);
	/**
	 * @var array
	 */
	protected static $anomalies_map = array(
		ilAtomQuery::ISOLATION_READ_UNCOMMITED => array(
			ilAtomQuery::ANO_LOST_UPDATES,
			ilAtomQuery::ANO_DIRTY_READ,
			ilAtomQuery::ANO_NON_REPEATED_READ,
			ilAtomQuery::ANO_PHANTOM,
		),
		ilAtomQuery::ISOLATION_READ_COMMITED   => array(
			ilAtomQuery::ANO_NON_REPEATED_READ,
			ilAtomQuery::ANO_PHANTOM,
		),
		ilAtomQuery::ISOLATION_REPEATED_READ   => array(
			ilAtomQuery::ANO_PHANTOM,
		),
		ilAtomQuery::ISOLATION_SERIALIZABLE    => array(),
	);
	/**
	 * @var int
	 */
	protected $isolation_level = ilAtomQuery::ISOLATION_SERIALIZABLE;
	/**
	 * @var array
	 */
	protected $tables = array();
	/**
	 * @var callable[]
	 */
	protected $queries = array();
	/**
	 * @var int
	 */
	protected $running_query = 0;
	/**
	 * @var \ilDBInterface
	 */
	protected $ilDBInstance;


	/**
	 * ilAtomQuery constructor.
	 *
	 * @param \ilDBInterface $ilDBInstance
	 * @param int $isolation_level currently only ISOLATION_SERIALIZABLE is available
	 */
	public function __construct(ilDBInterface $ilDBInstance, $isolation_level = ilAtomQuery::ISOLATION_SERIALIZABLE) {
		static::checkIsolationLevel($isolation_level);
		$this->ilDBInstance = $ilDBInstance;
		$this->isolation_level = $isolation_level;
	}

	//
	//
	//
	/**
	 * @return array
	 */
	public function getRisks() {
		return static::getPossibleAnomalies($this->getIsolationLevel());
	}


	/**
	 * Add table-names which are influenced by your queries, MyISAm has to lock those tables. Lock
	 *
	 * @param string $table_name
	 * @param int $lock_level use ilAtomQuery::LOCK_READ or ilAtomQuery::LOCK_WRITE
	 * @param bool $lock_sequence_too
	 * @throws \ilDatabaseException
	 */
	public function addTable($table_name, $lock_level, $lock_sequence_too = false) {
		if (!$table_name || !$this->ilDBInstance->tableExists($table_name)) {
			throw new ilDatabaseException('Table locks only work with existing tables');
		}
		if (!in_array($lock_level, array( ilAtomQuery::LOCK_READ, ilAtomQuery::LOCK_WRITE ))) {
			throw new ilDatabaseException('The current Isolation-level does not support the desired lock-level. use ilAtomQuery::LOCK_READ or ilAtomQuery::LOCK_WRITE');
		}
		$this->tables[] = array( $table_name, $lock_level, $lock_sequence_too );
	}


	/**
	 * @param $table_name
	 * @param bool $lock_sequence_too
	 */
	public function lockTableWrite($table_name, $lock_sequence_too = false) {
		$this->tables[] = array( $table_name, ilAtomQuery::LOCK_WRITE, $lock_sequence_too );
	}


	/**
	 * Every action on the database during this isolation has to be passed as Callable to ilAtomQuery.
	 * An example (Closure):
	 * $ilAtomQuery->addQueryClosure( function (ilDBInterface $ilDB) use ($new_obj_id, $current_id) {
	 *        $ilDB->doStuff();
	 *    });
	 *
	 *
	 * An example (Callable Class):
	 * class ilMyAtomQueryClass {
	 *      public function __invoke(ilDBInterface $ilDB) {
	 *          $ilDB->doStuff();
	 *      }
	 * }
	 *
	 * $ilAtomQuery->addQueryClosure(new ilMyAtomQueryClass());
	 *
	 * @param \callable $query
	 * @throws ilDatabaseException
	 */
	public function addQueryCallable(callable $query) {
		if (!$this->checkCallable($query)) {
			throw new ilDatabaseException('Please provide a Closure with your database-actions by adding with ilAtomQuery->addQueryClosure(function($ilDB) use ($my_vars) { $ilDB->doStuff(); });');
		}
		$this->queries[] = $query;
	}


	/**
	 * Fire your Queries
	 *
	 * @throws \ilDatabaseException
	 */
	abstract public function run();
	//
	//
	//
	/**
	 * @return int
	 */
	public function getIsolationLevel() {
		return $this->isolation_level;
	}


	/**
	 * @param $isolation_level
	 * @param $anomaly
	 * @return bool
	 * @throws \ilDatabaseException
	 */
	public static function isThereRiskThat($isolation_level, $anomaly) {
		static::checkIsolationLevel($isolation_level);
		static::checkAnomaly($anomaly);

		return in_array($anomaly, static::getPossibleAnomalies($isolation_level));
	}


	/**
	 * @param $isolation_level
	 * @return array
	 */
	public static function getPossibleAnomalies($isolation_level) {
		static::checkIsolationLevel($isolation_level);

		return self::$anomalies_map[$isolation_level];
	}


	/**
	 * @param $isolation_level
	 * @throws \ilDatabaseException
	 */
	public static function checkIsolationLevel($isolation_level) {
		// The following Isolations are currently not supported
		if (in_array($isolation_level, array(
			ilAtomQuery::ISOLATION_READ_UNCOMMITED,
			ilAtomQuery::ISOLATION_READ_COMMITED,
			ilAtomQuery::ISOLATION_REPEATED_READ,
		))) {
			throw new ilDatabaseException('This isolation-level is currently unsupported');
		}
		// Check if a available Isolation level is selected
		if (!in_array($isolation_level, self::$available_isolations_levels)) {
			throw new ilDatabaseException('Isolation-Level not available');
		}
	}


	/**
	 * @param $anomalie
	 * @throws \ilDatabaseException
	 */
	public static function checkAnomaly($anomalie) {
		if (!in_array($anomalie, self::$available_isolations_levels)) {
			throw new ilDatabaseException('Isolation-Level not available');
		}
	}


	/**
	 * @throws \ilDatabaseException
	 */
	protected function checkQueries() {
		foreach ($this->queries as $query) {
			if (!$this->checkCallable($query)) {
				throw new ilDatabaseException('Please provide a Closure with your database-actions by adding with ilAtomQuery->addQueryClosure(function($ilDB) use ($my_vars) { $ilDB->doStuff(); });');
			}
		}
	}


	/**
	 * @param callable $query
	 * @return bool
	 */
	public function checkCallable(callable $query) {
		if (!is_callable($query)) {
			return false; // Won't be triggered sidn type-hinting already checks this
		}
		if (is_array($query)) {
			return false;
		}
		if (is_string($query)) {
			return false;
		}
		$classname = get_class($query);
		$is_a_closure = $classname == 'Closure';
		if (!$is_a_closure) {
			$ref = new ReflectionClass($query);
			foreach ($ref->getMethods() as $method) {
				if ($method->getName() == '__invoke') {
					return true;
				}
			}

			return false;
		}
		if ($is_a_closure) {
			$ref = new ReflectionFunction($query);
			$parameters = $ref->getParameters();
			if (count($parameters) == 0) {
				return false;
			}
			foreach ($parameters as $parameter) {
				if ($parameter->getType() == 'ilDBInterface') {
					return true;
				}
			}

			return false;
		}

		return true;
	}


	/**
	 * @return bool
	 */
	protected function hasWriteLocks() {
		$has_write_locks = false;
		foreach ($this->tables as $table) {
			$lock_level = $table[1];
			if ($lock_level == ilAtomQuery::LOCK_WRITE) {
				$has_write_locks = true;
			}
		}

		return $has_write_locks;
	}


	/**
	 * @return array
	 */
	protected function getLocksForDBInstance() {
		$locks = array();
		foreach ($this->tables as $table) {
			$table_name = $table[0];
			$lock_level = $table[1];
			$lock_sequence_too = $table[2];
			$locks[] = array( 'name' => $table_name, 'type' => $lock_level );
			if ($lock_sequence_too && $this->ilDBInstance->sequenceExists($table_name)) {
				$locks[] = array( 'name' => $this->ilDBInstance->getSequenceName($table_name), 'type' => $lock_level );
			}
		}

		return $locks;
	}


	/**
	 * @throws ilDatabaseException
	 */
	protected function runQueries() {
		foreach ($this->queries as $i => $query) {
			if ($i < $this->running_query) {
				//				continue;
			}
			/**
			 * @var $query callable
			 */
			$query($this->ilDBInstance);

			$this->running_query ++;
		}
	}


	protected function checkBeforeRun() {
		$this->checkQueries();

		if ($this->hasWriteLocks() && $this->getIsolationLevel() != ilAtomQuery::ISOLATION_SERIALIZABLE) {
			throw new ilDatabaseException('The selected Isolation-level is not allowd when locking tables with write-locks');
		}
	}
}
