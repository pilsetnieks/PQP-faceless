<?php
	class Console extends PHPQuickProfiler
	{
		private static $CurrentLoop = 'Main';

		protected static $Data = array();
		//private static $Totals = array();

		protected static function Init()
		{
			self::$Data['Main'] = array(
				'Log' => array(),
				'Error' => array(),
				'Memory' => array(),
				'Speed' => array(),
				'Query' => array()
			);
		}

		public static function log($Message)
		{
			self::$Data[self::$CurrentLoop]['Log'][] = $Message;
		}

		public static function logError(Exception $E = null, $ErrorMessage = null)
		{
			if (!$E && !$ErrorMessage)
			{
				return false;
			}
		
			if ($ErrorMessage)
			{
				$Message = $ErrorMessage;
				$File = '';
				$Line = 0;
			}
			else
			{
				$Message = $E -> getMessage();
				$File = $E -> getFile();
				$Line = $E -> getLine();
			}

			self::$Data[self::$CurrentLoop]['Error'][] = array(
				'Message' => $Message,
				'File' => $File,
				'Line' => $Line
			);
		}

		public static function logMemory($Variable = null, $Name = null)
		{
			$Memory = memory_get_usage();
			if ($Variable)
			{
				$Memory = strlen(serialize($Variable));
			}

			self::$Data[self::$CurrentLoop]['Memory'][] = array(
				'Data' => $Memory,
				'Name' => $Name,
				'Type' => gettype($Variable)
			);
		}

		public static function logSpeed($Name)
		{
			self::$Data[self::$CurrentLoop]['Speed'][] = array(
				'Data' => self::getMicroTime(),
				'Name' => $Name
			);
		}

		public static function logQuery($SQL, $StartTime)
		{
			self::$Data[self::$CurrentLoop]['Query'][] = array(
				'SQL' => $SQL,
				'Time' => self::getMicroTime() - $StartTime
			);
		}
	}
?>