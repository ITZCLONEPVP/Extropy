<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

/**
 * Various Utilities used around the code
 */
namespace pocketmine\utils;

use pocketmine\entity\Entity;
use pocketmine\item\Item;

class Binary {

	const BIG_ENDIAN = 0x00;
	const LITTLE_ENDIAN = 0x01;

	private static function checkLength($str, $expected) {
		assert(($len = strlen($str)) === $expected, "Expected $expected bytes, got $len");
	}

	/**
	 * Reads a 3-byte big-endian number
	 *
	 * @param $str
	 *
	 * @return mixed
	 */
	public static function readTriad($str) {
		self::checkLength($str, 3);
		return @unpack("N", "\x00" . $str)[1];
	}

	/**
	 * Writes a 3-byte big-endian number
	 *
	 * @param $value
	 *
	 * @return string
	 */
	public static function writeTriad($value) {
		return substr(pack("N", $value), 1);
	}

	/**
	 * Reads a 3-byte little-endian number
	 *
	 * @param $str
	 *
	 * @return mixed
	 */
	public static function readLTriad($str) {
		self::checkLength($str, 3);
		return @unpack("V", $str . "\x00")[1];
	}

	/**
	 * Writes a 3-byte little-endian number
	 *
	 * @param $value
	 *
	 * @return string
	 */
	public static function writeLTriad($value) {
		return substr(pack("V", $value), 0, -1);
	}

	/**
	 * Writes a coded metadata string
	 *
	 * @param array $data
	 *
	 * @return string
	 */
	public static function writeMetadata(array $data) {
		$stream = new BinaryStream();
		$stream->putUnsignedVarInt(count($data));
		foreach($data as $key => $d) {
			$stream->putUnsignedVarInt($key);
			$stream->putUnsignedVarInt($d[0]);
			switch($d[0]) {
				case Entity::DATA_TYPE_BYTE:
					$stream->putByte($d[1]);
					break;
				case Entity::DATA_TYPE_SHORT:
					$stream->putLShort($d[1]);
					break;
				case Entity::DATA_TYPE_INT:
					$stream->putVarInt($d[1]);
					break;
				case Entity::DATA_TYPE_FLOAT:
					$stream->putLFloat($d[1]);
					break;
				case Entity::DATA_TYPE_STRING:
					$stream->putString($d[1]);
					break;
				case Entity::DATA_TYPE_SLOT:
					$stream->putSlot(Item::get($d[1][0], $d[1][2], $d[1][0]));
					break;
				case Entity::DATA_TYPE_POS:
					$stream->putBlockCoords($d[1][0], $d[1][1], $d[1][2]);
					break;
				case Entity::DATA_TYPE_LONG:
					$stream->putVarInt($d[1]);
					break;
				case Entity::DATA_TYPE_VECTOR3F:
					$stream->putVector3f($d[1][0], $d[1][1], $d[1][2]);
					break;
			}
		}

		return $stream->getBuffer();
	}

	/**
	 * Writes an unsigned/signed byte
	 *
	 * @param $c
	 *
	 * @return string
	 */
	public static function writeByte($c) {
		return chr($c);
	}

	/**
	 * Writes a 16-bit signed/unsigned little-endian number
	 *
	 * @param $value
	 *
	 * @return string
	 */
	public static function writeLShort($value) {
		return pack("v", $value);
	}

	public static function writeLInt($value) {
		return pack("V", $value);
	}

	public static function writeLFloat($value) {
		return ENDIANNESS === self::BIG_ENDIAN ? strrev(pack("f", $value)) : pack("f", $value);
	}

	public static function writeLLong($value) {
		return strrev(self::writeLong($value));
	}

	public static function readVarInt($stream) {
		$shift = PHP_INT_SIZE === 8 ? 63 : 31;
		$raw = self::readUnsignedVarInt($stream);
		$temp = ((($raw << $shift) >> $shift) ^ $raw) >> 1;
		return $temp ^ ($raw & (1 << $shift));
	}

	public static function readUnsignedVarInt(BinaryStream $stream) {
		$value = 0;
		$i = 0;
		do {
			if($i > 63 ) throw new \InvalidArgumentException("Varint did't terminate after 10 bytes!");;
			$value |= ((($b = $stream->getByte()) & 0x7f) << $i);
			$i += 7;
		} while($b & 0x80);

		return $value;
	}

	public static function writeVarInt($v) {
		return self::writeUnsignedVarInt(($v << 1) ^ ($v >> (PHP_INT_SIZE === 8 ? 63 : 31)));
	}

	public static function writeUnsignedVarInt($v) {
		$buf = "";
		$loops = 0;
		do {
			if($loops > 9) throw new \InvalidArgumentException("Varint cannot be longer than 10 bytes!");
			$w = $v & 0x7f;
			if(($v >> 7) !== 0) {
				$w = $v | 0x80;
			}
			$buf .= self::writeByte($w);
			$v = (($v >> 7) & (PHP_INT_MAX >> 6));
			$loops++;
		} while($v);

		return $buf;
	}

	public static function writeLong($value) {
		if(PHP_INT_SIZE === 8) {
			return pack("NN", $value >> 32, $value & 0xFFFFFFFF);
		} else {
			$x = "";

			if(bccomp($value, "0") == -1) {
				$value = bcadd($value, "18446744073709551616");
			}

			$x .= self::writeShort(bcmod(bcdiv($value, "281474976710656"), "65536"));
			$x .= self::writeShort(bcmod(bcdiv($value, "4294967296"), "65536"));
			$x .= self::writeShort(bcmod(bcdiv($value, "65536"), "65536"));
			$x .= self::writeShort(bcmod($value, "65536"));

			return $x;
		}
	}

	/**
	 * Writes a 16-bit signed/unsigned big-endian number
	 *
	 * @param $value
	 *
	 * @return string
	 */
	public static function writeShort($value) {
		return pack("n", $value);
	}

	/**
	 * Reads a metadata coded string
	 *
	 * @param      $value
	 * @param bool $types
	 *
	 * @return array
	 */
	public static function readMetadata($value, $types = false) {
		$stream = new BinaryStream();
		$stream->setBuffer($value);
		$count = $stream->getUnsignedVarInt();
		$data = [];
		for($i = 0; $i < $count; $i++) {
			$key = $stream->getUnsignedVarInt();
			$type = $stream->getUnsignedVarInt();
			$value = null;
			switch($type) {
				case Entity::DATA_TYPE_BYTE:
					$value = $stream->getByte();
					break;
				case Entity::DATA_TYPE_SHORT:
					$value = $stream->getLShort(true);
					break;
				case Entity::DATA_TYPE_INT:
					$value = $stream->getVarInt();
					break;
				case Entity::DATA_TYPE_FLOAT:
					$stream->getLFloat();
					break;
				case Entity::DATA_TYPE_STRING:
					$stream->getString();
					break;
				case Entity::DATA_TYPE_SLOT:
					$item = $stream->getSlot();
					$value[0] = $item->getId();
					$value[1] = $item->getCount();
					$value[2] = $item->getDamage();
					break;
				case Entity::DATA_TYPE_POS:
					$value = [0, 0, 0];
					$stream->getBlockCoords($value[0], $value[1], $value[2]);
					break;
				case Entity::DATA_TYPE_LONG:
					$value = $stream->getVarInt();
					break;
				case Entity::DATA_TYPE_VECTOR3F:
					$value = [0.0, 0.0, 0.0];
					$stream->getVector3f($value[0], $value[1], $value[2]);
					break;
				default:
					$value = [];
			}
			if($types) {
				$data[$key] = [$value, $type];
			} else {
				$data[$key] = $value;
			}
		}

		return $data;
	}

	/**
	 * Reads an unsigned/signed byte
	 *
	 * @param string $c
	 * @param bool $signed
	 *
	 * @return int
	 */
	public static function readByte($c, $signed = true) {
		self::checkLength($c, 1);
		$b = ord($c{0});

		if($signed) {
			if(PHP_INT_SIZE === 8) {
				return $b << 56 >> 56;
			} else {
				return $b << 24 >> 24;
			}
		} else {
			return $b;
		}
	}

	/**
	 * Reads a 16-bit unsigned little-endian number
	 *
	 * @param      $str
	 *
	 * @return int
	 */
	public static function readLShort($str) {
		self::checkLength($str, 2);
		return @unpack("v", $str)[1];
	}

	public static function readLInt($str) {
		self::checkLength($str, 4);
		if(PHP_INT_SIZE === 8) {
			return @unpack("V", $str)[1] << 32 >> 32;
		} else {
			return @unpack("V", $str)[1];
		}
	}

	public static function readLFloat($str) {
		self::checkLength($str, 4);
		return ENDIANNESS === self::BIG_ENDIAN ? @unpack("f", strrev($str))[1] : @unpack("f", $str)[1];
	}

	public static function readLLong($str) {
		return self::readLong(strrev($str));
	}

	public static function readLong($x) {
		self::checkLength($x, 8);
		if(PHP_INT_SIZE === 8) {
			$int = @unpack("N*", $x);

			return ($int[1] << 32) | $int[2];
		} else {
			$value = "0";
			for($i = 0; $i < 8; $i += 2) {
				$value = bcmul($value, "65536", 0);
				$value = bcadd($value, self::readShort(substr($x, $i, 2)), 0);
			}

			if(bccomp($value, "9223372036854775807") == 1) {
				$value = bcadd($value, "-18446744073709551616");
			}

			return $value;
		}
	}

	/**
	 * Reads a 16-bit unsigned big-endian number
	 *
	 * @param $str
	 *
	 * @return int
	 */
	public static function readShort($str) {
		self::checkLength($str, 2);
		return @unpack("n", $str)[1];
	}

	/**
	 * Reads a byte boolean
	 *
	 * @param $b
	 *
	 * @return bool
	 */
	public static function readBool($b) {
		return self::readByte($b, false) === 0 ? false : true;
	}

	/**
	 * Writes a byte boolean
	 *
	 * @param $b
	 *
	 * @return bool|string
	 */
	public static function writeBool($b) {
		return self::writeByte($b === true ? 1 : 0);
	}

	/**
	 * Reads a 16-bit signed big-endian number
	 *
	 * @param $str
	 *
	 * @return int
	 */
	public static function readSignedShort($str) {
		self::checkLength($str, 2);
		if(PHP_INT_SIZE === 8) {
			return @unpack("n", $str)[1] << 48 >> 48;
		} else {
			return @unpack("n", $str)[1] << 16 >> 16;
		}
	}

	/**
	 * Reads a 16-bit signed little-endian number
	 *
	 * @param      $str
	 *
	 * @return int
	 */
	public static function readSignedLShort($str) {
		self::checkLength($str, 2);
		if(PHP_INT_SIZE === 8) {
			return @unpack("v", $str)[1] << 48 >> 48;
		} else {
			return @unpack("v", $str)[1] << 16 >> 16;
		}
	}

	public static function readInt($str) {
		self::checkLength($str, 4);
		if(PHP_INT_SIZE === 8) {
			return @unpack("N", $str)[1] << 32 >> 32;
		} else {
			return @unpack("N", $str)[1];
		}
	}

	public static function writeInt($value) {
		return pack("N", $value);
	}

	public static function readFloat($str) {
		self::checkLength($str, 4);
		return ENDIANNESS === self::BIG_ENDIAN ? @unpack("f", $str)[1] : @unpack("f", strrev($str))[1];
	}

	public static function writeFloat($value) {
		return ENDIANNESS === self::BIG_ENDIAN ? pack("f", $value) : strrev(pack("f", $value));
	}

	public static function printFloat($value) {
		return preg_replace("/(\\.\\d+?)0+$/", "$1", sprintf("%F", $value));
	}

	public static function readDouble($str) {
		self::checkLength($str, 8);
		return ENDIANNESS === self::BIG_ENDIAN ? @unpack("d", $str)[1] : @unpack("d", strrev($str))[1];
	}

	public static function writeDouble($value) {
		return ENDIANNESS === self::BIG_ENDIAN ? pack("d", $value) : strrev(pack("d", $value));
	}

	public static function readLDouble($str) {
		self::checkLength($str, 8);
		return ENDIANNESS === self::BIG_ENDIAN ? @unpack("d", strrev($str))[1] : @unpack("d", $str)[1];
	}

	public static function writeLDouble($value) {
		return ENDIANNESS === self::BIG_ENDIAN ? strrev(pack("d", $value)) : pack("d", $value);
	}

}
