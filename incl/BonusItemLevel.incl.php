<?php

namespace Newsstand;

class BonusItemLevel
{
    private static $bonusCurveCache = [];
    private static $bonusLevelCache = [];
    private static $curvePointCache = [];

    public static function init($db) {
        static::$bonusCurveCache = [];
        $stmt = $db->prepare('select id, levelcurve, levelcurvepriority from tblDBCItemBonus where levelcurve is not null');
        $stmt->execute();
        $id = $curve = $prio = null;
        $stmt->bind_result($id, $curve, $prio);
        while ($stmt->fetch()) {
            static::$bonusCurveCache[$id] = [$curve, $prio];
        }
        $stmt->close();

        static::$curvePointCache = [];
        $stmt = $db->prepare('select curve, `key`, `value` from tblDBCCurvePoint cp join (select distinct levelcurve from tblDBCItemBonus) curves on cp.curve = curves.levelcurve order by curve, `step`');
        $stmt->execute();
        $curve = $key = $value = null;
        $stmt->bind_result($curve, $key, $value);
        while ($stmt->fetch()) {
            static::$curvePointCache[$curve][$key] = $value;
        }
        $stmt->close();

        static::$bonusLevelCache = [];
        $stmt = $db->prepare('select id, level from tblDBCItemBonus where level is not null');
        $stmt->execute();
        $id = $level = null;
        $stmt->bind_result($id, $level);
        while ($stmt->fetch()) {
            static::$bonusLevelCache[$id] = $level;
        }
        $stmt->close();
    }

    public static function GetBonusItemLevel($bonuses, $defaultItemLevel, $lootedLevel) {
        $levelSum = $defaultItemLevel;

        $currentCurve = null;
        $currentPrio = null;

        foreach ($bonuses as $bonus) {
            if (isset(static::$bonusCurveCache[$bonus])) {
                list($curve, $prio) = static::$bonusCurveCache[$bonus];
                if (is_null($currentPrio) || $currentPrio > $prio) {
                    $currentCurve = $curve;
                    $currentPrio = $prio;
                }
            }
            $levelSum += isset(static::$bonusLevelCache[$bonus]) ? static::$bonusLevelCache[$bonus] : 0;
        }

        if (!is_null($currentCurve)) {
            return static::GetCurvePoint($currentCurve, $lootedLevel);
        }

        return $levelSum;
    }

    private static function GetCurvePoint($curve, $point) {
        if (!isset(static::$curvePointCache[$curve])) {
            return null;
        }

        reset(static::$curvePointCache[$curve]);
        $lastKey = key(static::$curvePointCache[$curve]);
        $lastValue = static::$curvePointCache[$curve][$lastKey];

        if ($lastKey > $point) {
            return $lastValue;
        }

        foreach (static::$curvePointCache[$curve] as $key => $value) {
            if ($point == $key) {
                return $value;
            }
            if ($point < $key) {
                return round(($value - $lastValue) / ($key - $lastKey) * ($point - $lastKey) + $lastValue);
            }
            $lastKey = $key;
            $lastValue = $value;
        }

        return $lastValue;
    }
}
