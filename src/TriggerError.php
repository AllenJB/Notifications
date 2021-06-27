<?php

namespace AllenJB\Notifications;

class TriggerError
{

    /** @noinspection PhpUndefinedFieldInspection PhpUnusedLocalVariableInspection */
    public static function notice(): void
    {
        $v = null;
        $a = $v->nonExistant;
    }


    /** @noinspection PhpExpressionResultUnusedInspection */
    public static function warning(): void
    {
        $v = null;
        count($v);
    }


    /** @noinspection PhpUndefinedFunctionInspection */
    public static function fatalError(): void
    {
        thisFunctionIsNotDefined();
    }


    /** @noinspection PhpDivisionByZeroInspection PhpExpressionResultUnusedInspection */
    public static function thrownError(): void
    {
        2 % 0;
    }


    /** @noinspection OnlyWritesOnParameterInspection */
    public static function oom(): void
    {
        $a = '';
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
        function callSelf()
        {
            callSelf();
        }

        callSelf();
    }


    /** @noinspection PhpUnusedLocalVariableInspection */
    public static function timeout(): void
    {
        while (true) {
            $v = password_hash('dummy value', PASSWORD_BCRYPT, ['cost' => 30]);
        }
    }

}
