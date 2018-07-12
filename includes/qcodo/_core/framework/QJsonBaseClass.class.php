<?php
	abstract class QJsonBaseClass extends QBaseClass {
		/**
		 * @var mixed[] contains the values of each property
		 */
		protected $mixPropertiesDictionary = array();
		protected static $_Model;

		public static function JsonDecode($mixJson) {
			throw new QCallerException('JsonDecode() Not Implemented: ' . get_called_class());
		}

		protected static function JsonDecodeForClass($strClassName, $mixJson) {
			$objToReturn = new $strClassName();

			if ($mixJson instanceof stdClass) {
				$objJson = $mixJson;
			} else {
				$objJson = json_decode($mixJson);

				// If there was an error, this may have returned as null
				if (is_null($objJson)) {
//					$intJsonLastError = json_last_error();
//					$strJsonLastErrorMessage = json_last_error_msg();
//
//					if ($intJsonLastError != JSON_ERROR_NONE) {
//						throw new Exception('JSON Decode (' . $intJsonLastError . ') - ' . $strJsonLastErrorMessage);
//					}
				}
			}

			if (is_null($objJson)) return null;
			if ($objJson === false) return false;
			if ($objJson === true) return true;

			// Go Through Each Property in the Model
			foreach ($objToReturn::$_Model as $strName => $mixType) {

				// If we have defined this Property in the JSON...
				if (isset($objJson->$strName)) {

					// Is it NULL?
					if (is_null($objJson->$strName)) {
						$objToReturn->mixPropertiesDictionary[$strName] = null;

					// Is the TYPE: Array?
					} else if (is_array($mixType)) {
						$objToReturn->mixPropertiesDictionary[$strName] = self::JsonDecodeArrayForType($mixType[0], $objJson->$strName);

					// Otherwise, operate based on TYPE
					} else switch ($mixType) {
						case 'boolean':
						case 'date':
						case 'datetime':
						case 'float':
						case 'integer':
						case 'string':
							$objToReturn->mixPropertiesDictionary[$strName] = $objJson->$strName;
							break;

						default:
							$strFullyQualifiedClassName = substr($strClassName, 0, strrpos($strClassName, '\\')) . '\\' . $mixType;
							if (!is_subclass_of($strFullyQualifiedClassName, 'QJsonBaseClass')) throw new QCallerException('Unsupported Type for ' . $strClassName . '::' . $strName . ': ' . $mixType);
							$objToReturn->mixPropertiesDictionary[$strName] = $strFullyQualifiedClassName::JsonDecode($objJson->$strName);
							break;
					}
				}
			}

			return $objToReturn;
		}

		public static function JsonDecodeArrayForType($mixType, $mixJson) {
			if (is_array($mixJson)) {
				$objJsonArray = $mixJson;
			} else if (is_object($mixJson) && (get_class($mixJson) == 'stdClass')) {
				$objJsonArray = array();
				foreach ($mixJson as $mixKey => $mixValue) $objJsonArray[$mixKey] = $mixValue;
			} else {
				$objJsonArray = json_decode($mixJson);
			}

			if (is_null($objJsonArray)) return null;
			if (!is_array($objJsonArray)) throw new QCallerException('JsonDecodeArray() cannot decode non-array value');

			if (is_array($mixType)) {
				$arrToReturn = new ArrayObject();
				foreach ($objJsonArray as $objJson) $arrToReturn[] = self::JsonDecodeArrayForType($mixType[0], $objJson);

			} else switch ($mixType) {
				case 'boolean':
				case 'date':
				case 'datetime':
				case 'float':
				case 'integer':
				case 'string':
					$arrToReturn = new ArrayObject($objJsonArray);
					break;

				default:
					$arrToReturn = new ArrayObject();
					if (!is_subclass_of($mixType, 'QJsonBaseClass')) throw new Exception('JsonDecodeArray() cannot decode Unsupported Type: ' . $mixType);
					foreach ($objJsonArray as $objJson) $arrToReturn[] = $mixType::JsonDecode($objJson);
					break;
			}

			return $arrToReturn;
		}

		public function JsonEncode() {
			return json_encode($this->GetJsonHelper());
		}

		public static function JsonEncodeArray($objArray) {
			if (is_null($objArray)) return json_encode(null);

			if (!is_array($objArray) && !($objArray instanceof ArrayObject)) throw new QCallerException('Cannot GetJsonForArray() on a non-array parameter');

			$objArray = self::GetJsonArrayHelper($objArray);
			if ($objArray instanceof ArrayObject) $objArray = $objArray->GetArrayCopy();
			return json_encode($objArray);
		}

		protected function GetJsonHelper() {
			$objToExport = $this->mixPropertiesDictionary;

			foreach ($objToExport as $mixIndex => $mixValue) {
				if ($mixValue instanceof QJsonBaseClass) {
					$objToExport[$mixIndex] = $mixValue->GetJsonHelper();
				} else if ($mixValue instanceof ArrayObject) {
					$objToExport[$mixIndex] = self::GetJsonArrayHelper($mixValue->getArrayCopy());
				} else if (is_array($mixValue)) {
					$objToExport[$mixIndex] = self::GetJsonArrayHelper($mixValue);
				}
			}

			return $objToExport;
		}

		protected static function GetJsonArrayHelper($objArray) {
			foreach ($objArray as $mixIndex => $mixValue) {
				if ($mixValue instanceof QJsonBaseClass) {
					$objArray[$mixIndex] = $mixValue->GetJsonHelper();
				} else if ($mixValue instanceof ArrayObject) {
					$objArray[$mixIndex] = self::GetJsonArrayHelper($mixValue->getArrayCopy());
				} else if (is_array($mixValue)) {
					$objArray[$mixIndex] = self::GetJsonArrayHelper($mixValue);
				}
			}

			return $objArray;
		}

		public function IsPropertySet($strName) {
			$strIndex = strtolower(substr($strName, 0, 1)) . substr($strName, 1);

			return array_key_exists($strIndex, $this::$_Model);
		}

		public function __get($strName) {
			$strIndex = strtolower(substr($strName, 0, 1)) . substr($strName, 1);

			if (array_key_exists($strIndex, $this::$_Model)) {
				if (array_key_exists($strIndex, $this->mixPropertiesDictionary)) {
					switch ($this::$_Model[$strIndex]) {
						case 'date':
							if (!$this->mixPropertiesDictionary[$strIndex]) return null;
							$dttToReturn = new QDateTime($this->mixPropertiesDictionary[$strIndex]);
							$dttToReturn->SetTime(null, null, null);
							return $dttToReturn;

						case 'datetime':
							if (!$this->mixPropertiesDictionary[$strIndex]) return null;
							return new QDateTime($this->mixPropertiesDictionary[$strIndex]);

						default:
							return $this->mixPropertiesDictionary[$strIndex];
					}
				} else
					return null;
			} else {
				try {
					return parent::__get($strName);
				} catch (QCallerException $objExc) {
					$objExc->IncrementOffset();
					throw $objExc;
				}
			}
		}

		public function __set($strName, $mixValue) {
			$strIndex = strtolower(substr($strName, 0, 1)) . substr($strName, 1);

			if (array_key_exists($strIndex, $this::$_Model)) {
				// Special Case for NULL
				if (is_null($mixValue)) {
					$this->mixPropertiesDictionary[$strIndex] = null;
					return null;
				}

				// Otherwise, we set based on the definition
				$mixType = $this::$_Model[$strIndex];

				if (is_array($mixType)) {
					if (is_array($mixValue))
						$this->mixPropertiesDictionary[$strIndex] = new ArrayObject($mixValue);
					else if ($mixValue instanceof ArrayObject)
						$this->mixPropertiesDictionary[$strIndex] = $mixValue;
					else
						throw new QCallerException('Invalid Array Object: ' . $strName);
					return $mixValue;

				} else switch ($mixType) {
					case 'boolean':
						$this->mixPropertiesDictionary[$strIndex] = QType::Cast($mixValue, QType::Boolean);
						return $mixValue;

					case 'date':
						if ($mixValue instanceof QDateTime) {
							$this->mixPropertiesDictionary[$strIndex] = $mixValue->ToString('YYYY-MM-DD');
						} else if (!$mixValue) {
							$this->mixPropertiesDictionary[$strIndex] = null;
						} else {
							$this->mixPropertiesDictionary[$strIndex] = $mixValue;
						}
						return $mixValue;

					case 'datetime':
						if ($mixValue instanceof QDateTime) {
							$this->mixPropertiesDictionary[$strIndex] = $mixValue->ToString('YYYY-MM-DDThhhh:mm:sstttttt');
						} else if (!$mixValue) {
							$this->mixPropertiesDictionary[$strIndex] = null;
						} else {
							$this->mixPropertiesDictionary[$strIndex] = $mixValue;
						}
						return $mixValue;

					case 'float':
						$this->mixPropertiesDictionary[$strIndex] = QType::Cast($mixValue, QType::Float);
						return $mixValue;

					case 'integer':
						$this->mixPropertiesDictionary[$strIndex] = QType::Cast($mixValue, QType::Integer);
						return $mixValue;

					case 'string':
						$this->mixPropertiesDictionary[$strIndex] = $mixValue;
						return $mixValue;

					default:
						$strFullyQualifiedClassName = substr(get_called_class(), 0, strrpos(get_called_class(), '\\')) . '\\' . $this::$_Model[$strIndex];

						if ($mixValue instanceof $strFullyQualifiedClassName) {
							$this->mixPropertiesDictionary[$strIndex] = $mixValue;
							return $mixValue;
						}

						throw new QCallerException('Mismatched DataType for field [' . get_called_class() . '::'. $strIndex . ']: ' . $this::$_Model[$strIndex]);
				}
			} else {
				try {
					return parent::__set($strName, $mixValue);
				} catch (QCallerException $objExc) {
					$objExc->IncrementOffset();
					throw $objExc;
				}
			}
		}
	}