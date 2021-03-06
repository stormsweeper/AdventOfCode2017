<?php

class IntPuterV5 {
    const MAX_RAW_OPCODE = 22299;

    const OPCODE_ADD = 1;
    const OPCODE_MULTIPLY = 2;
    const OPCODE_INPUT = 3;
    const OPCODE_OUTPUT = 4;
    const OPCODE_JUMP_IF_TRUE = 5;
    const OPCODE_JUMP_IF_FALSE = 6;
    const OPCODE_LESS_THAN = 7;
    const OPCODE_EQUALS = 8;
    const OPCODE_RELBASE_ADJUST = 9;
    const OPCODE_EXIT = 99;
    const OPCODE_INVALID = -1;
    const VALID_OPCODES = [
        self::OPCODE_ADD,
        self::OPCODE_MULTIPLY,
        self::OPCODE_INPUT,
        self::OPCODE_OUTPUT,
        self::OPCODE_JUMP_IF_TRUE,
        self::OPCODE_JUMP_IF_FALSE,
        self::OPCODE_LESS_THAN,
        self::OPCODE_EQUALS,
        self::OPCODE_RELBASE_ADJUST,
        self::OPCODE_EXIT,
    ];

    const PARAM_MODE_POS = 0;
    const PARAM_MODE_IMM = 1;
    const PARAM_MODE_REL = 2;

    private $registers = [];
    private $cursor = 0;
    private $rel_base = 0;

    private $input_callback = 'IntPuterV5::getUserInput';
    private $output_callback = 'IntPuterV5::consoleOutput';

    function loadProgram(array $program): void {
        $this->registers = $program;
    }

    function run(): void {
        $this->cursor = 0;
        while ($this->cursor < count($this->registers)) {
            $rawopcode = $this->readNextRegister();
            list($opcode, $a_mode, $b_mode, $c_mode) = $this->parseOpcodeAndModes($rawopcode);
            if ($opcode === self::OPCODE_INVALID) {
                throw new RuntimeException('Unknown opcode: ' . $rawopcode);
            }
            if ($opcode === self::OPCODE_EXIT) {
                break;
            } elseif ($opcode === self::OPCODE_ADD) {
                $a = $this->realizeValue($this->readNextRegister(), $a_mode);
                $b = $this->realizeValue($this->readNextRegister(), $b_mode);
                $c = $this->readNextRegister();
                $this->setRegister($c, $a + $b, $c_mode);
            } elseif ($opcode === self::OPCODE_MULTIPLY) {
                $a = $this->realizeValue($this->readNextRegister(), $a_mode);
                $b = $this->realizeValue($this->readNextRegister(), $b_mode);
                $c = $this->readNextRegister();
                $this->setRegister($c, $a * $b, $c_mode);
            } elseif ($opcode === self::OPCODE_INPUT) {
                $a = $this->readNextRegister();
                $this->setRegister($a, $this->readInput(), $a_mode);
            } elseif ($opcode === self::OPCODE_OUTPUT) {
                $a = $this->realizeValue($this->readNextRegister(), $a_mode);
                $this->output($a);
            } elseif ($opcode === self::OPCODE_JUMP_IF_TRUE) {
                $a = $this->realizeValue($this->readNextRegister(), $a_mode);
                $b = $this->realizeValue($this->readNextRegister(), $b_mode);
                if ($a !== 0) {
                    $this->jumpTo($b);
                }
            } elseif ($opcode === self::OPCODE_JUMP_IF_FALSE) {
                $a = $this->realizeValue($this->readNextRegister(), $a_mode);
                $b = $this->realizeValue($this->readNextRegister(), $b_mode);
                if ($a === 0) {
                    $this->jumpTo($b);
                }
            } elseif ($opcode === self::OPCODE_LESS_THAN) {
                $a = $this->realizeValue($this->readNextRegister(), $a_mode);
                $b = $this->realizeValue($this->readNextRegister(), $b_mode);
                $c = $this->readNextRegister();
                $this->setRegister($c, (int)$a < $b, $c_mode);
            } elseif ($opcode === self::OPCODE_EQUALS) {
                $a = $this->realizeValue($this->readNextRegister(), $a_mode);
                $b = $this->realizeValue($this->readNextRegister(), $b_mode);
                $c = $this->readNextRegister();
                $this->setRegister($c, (int)$a === $b, $c_mode);
            } elseif ($opcode === self::OPCODE_RELBASE_ADJUST) {
                $a = $this->realizeValue($this->readNextRegister(), $a_mode);
                $this->adjustRelbase($a);
            }
        }
    }

    function parseOpcodeAndModes(int $rawopcode): array {
        // maybe not required, but strict reading of the format suggests it only looks at digits
        $rawopcode = abs($rawopcode);
        $opcode = $rawopcode % 100;
        if ($rawopcode > self::MAX_RAW_OPCODE || !in_array($opcode, self::VALID_OPCODES)) {
            return [self::OPCODE_INVALID, false, false, false];
        }
        $modes = str_pad((string)$rawopcode, 5, '0', STR_PAD_LEFT);
        return [$opcode, intval($modes[2]), intval($modes[1]), intval($modes[0])];
    }

    static function getUserInput(): int {
        $asint = null;
        while (!isset($asint)) {
            $input = trim(readline('? '));
            $asint = (int)$input;
            if ((string)$asint !== $input) {
                $asint = null;
            }
        }
        return $asint;
    }

    function readInput(): int {
        return ($this->input_callback)();
    }

    function setInputCallback(callable $input_callback): void {
        $this->input_callback = $input_callback;
    }

    function setOutputCallback(callable $output_callback): void {
        $this->output_callback = $output_callback;
    }

    function output(int $value): void {
        ($this->output_callback)($value);
    }

    static function consoleOutput(int $value): void {
        echo $value . "\n";
    }

    function readNextRegister(): int {
        return $this->getRegister($this->cursor++);
    }

    function realizeValue(int $param, int $mode): int {
        if ($mode === self::PARAM_MODE_POS || $mode === self::PARAM_MODE_REL) {
            $param = $this->getRegister($param, $mode === self::PARAM_MODE_REL);
        }
        return $param;
    }

    function getRegister(int $pos, bool $relative = false): int {
        if ($relative) {
            $pos += $this->rel_base;
        }
        if ($pos < 0) {
            throw new InvalidArgumentException('Invalid position: ' . $pos);
        }
        if (!array_key_exists($pos, $this->registers)) {
            return 0;
        }
        return $this->registers[$pos];
    }

    function setRegister(int $pos, int $val, int $mode): void {
        if ($mode === self::PARAM_MODE_REL) {
            $pos += $this->rel_base;
        }
        if ($pos < 0) {
            throw new InvalidArgumentException('Invalid position: ' . $pos);
        }
        $this->registers[$pos] = $val;
    }

    function adjustRelbase(int $value) {
        $this->rel_base += $value;
    }

    function dumpMemory(): array {
        return $this->registers;
    }

    function jumpTo(int $pos, bool $relative = false): void {
        if ($relative) {
            $pos += $this->rel_base;
        }
        if ($pos < 0) {
            throw new InvalidArgumentException('Invalid position: ' . $pos);
        }
        $this->cursor = $pos;
    }

    function currentInstruction(): int {
        return $this->cursor;
    }

}