<?php

namespace AllenJB\Notifications;

class TriggerError
{

    /** @noinspection PhpExpressionAlwaysNullInspection PhpUnusedLocalVariableInspection */
    public static function notice(): void
    {
        $v = null;
        // @phpstan-ignore-next-line
        $a = $v->nonExistant;
    }


    /** @noinspection PhpExpressionResultUnusedInspection */
    public static function warning(): void
    {
        $v = null;
        // @phpstan-ignore-next-line
        count($v);
    }


    /** @noinspection PhpUndefinedFunctionInspection */
    public static function fatalError(): void
    {
        // @phpstan-ignore-next-line
        thisFunctionIsNotDefined();
    }


    /** @noinspection PhpDivisionByZeroInspection PhpExpressionResultUnusedInspection */
    public static function thrownError(): void
    {
        // @phpstan-ignore-next-line
        2 % 0;
    }


    /** @noinspection OnlyWritesOnParameterInspection */
    public static function oom(): void
    {
        $a = '';
        // @phpstan-ignore-next-line
        while (true) {
            $a .= str_repeat("Hello", 1024 * 1024);
        }
    }


    /**
     * Exhaust the stack with an infinite tail recursion function call.
     * As of writing (PHP 7.3) this causes a SegFault
     */
    public static function stackOverflow(): void
    {
        static::stackOverflow();
    }


    /** @noinspection PhpUnusedLocalVariableInspection */
    public static function timeout(): void
    {
        // @phpstan-ignore-next-line
        while (true) {
            $v = password_hash('dummy value', PASSWORD_BCRYPT, ['cost' => 30]);
        }
    }

}
