<?php
namespace ProgressBarCLI;

/**
 * Class ProgressBarCLI
 * @package ProgressBarCLI
 *
 *
 * @property string currentPosChar
 * @property string doneBarChar
 * @property string remainingBarChar
 * @property string format
 */
class ProgressBarCLI
{
    const ENCODING = 'utf-8';
    const CURSOR_HIDE = "\033[?25l";
    const CURSOR_SHOW = "\033[?25h";
    const COLOR_NORMAL = "\033[0m";
    const COLOR_RED = "\033[0;31m";
    const COLOR_GREEN = "\033[0;32m";

    private $width;
    private $max;
    private $stream;
    private $current;
    private $advancement;
    private $properties = [
        // формат полосы, по-умолчанию "#current#/#max# [#bar#] #percent# #eta#"
        'format',
        'doneBarChar', // символ завершенной точки, по-умолчанию "="
        'currentPosChar', // символ текущей точки выполнения, по-умолчанию ">"
        'remainingBarChar', // символ следующей точки выполнения, по-умолчанию "-"
    ];

    /**
     * ProgressBarCLI constructor.
     * @param int $max максимальное количество checkpoints
     * @param int $width ширина строки в символах
     * @param resource $stream
     */
    public function __construct($max, $width = 80, $stream = STDOUT)
    {
        $this->max = (int)$max;
        $this->width = (int)$width;
        $this->stream = $stream;
        $this->format = '#current#/#max# [#bar#] #percent# #eta#';
        $this->remainingBarChar = '-';
        $this->doneBarChar = '=';
        $this->currentPosChar = '>';
        $this->hideCursor()->reset();
    }

    public function __get($name)
    {
        if (in_array($name, $this->properties)) {
            return $this->$name;
        }
        throw new \InvalidArgumentException('method __get: ' . $name . ' not found');
    }

    public function __set($name, $value)
    {
        if (!in_array($name, $this->properties)) {
            throw new \InvalidArgumentException('method __set: ' . $name . ' not found');
        }
        $this->$name = $value;
    }

    /**
     * Прибавить на единицу значение текущей точки
     * @return $this
     */
    public function advance()
    {
        $this->current++;
        $this->update($this->current);
        return $this;
    }

    /**
     * Остановка прогресса
     */
    public function stop()
    {
        $this->display(true);
    }

    /**
     * Текущая точка
     * @param $current
     * @return $this
     */
    public function update($current)
    {
        $this->current = (int)$current;
        $this->current = ($this->max < $this->current) ? $this->max : $this->current;
        $this->advancement[$this->current] = time();
        $this->display();
        return $this;
    }

    /**
     * Вывод на экран
     * @param bool $stop
     * @return $this
     */
    private function display($stop = false)
    {
        $isEnd = $stop || ($this->current == $this->max);
        $buffer = $this->buildString();
        $eolCharacter = "\r";
        if ($isEnd) {
            $pattern = '/(\d{2}:\d{2}:\d{2})/';
            if ($stop) { // время красным
                $replace = self::COLOR_RED . '$1' . self::COLOR_NORMAL;
                $buffer = preg_replace($pattern, $replace, $buffer);
            } else { // заменим время на complete
                $complete = self::COLOR_GREEN . 'Complete' . self::COLOR_NORMAL;
                $buffer = preg_replace($pattern, $complete, $buffer);
            }
            $this->reset();
            $eolCharacter = "\n";
        }
        fprintf($this->stream, '%s', $buffer . $eolCharacter);
        return $this;
    }

    /**
     * Сбор строки
     * @return string
     */
    private function buildString()
    {
        $buffer = $this->format;
        $buffer = str_replace('#current#', $this->current, $buffer);
        $buffer = str_replace('#max#', $this->max, $buffer);
        $buffer = str_replace('#percent#', $this->percentString(), $buffer);
        $buffer = str_replace('#eta#', $this->getETA(), $buffer);
        $buffer = str_replace('#bar#', $this->getBar($buffer), $buffer);
        return str_pad($buffer, $this->width, ' ', STR_PAD_RIGHT);
    }

    /**
     * Скрыть курсор
     * @return $this
     * @internal param resource $stream
     */
    private function hideCursor()
    {
        $stream = $this->stream;
        fprintf($this->stream, self::CURSOR_HIDE);
        register_shutdown_function(function () use ($stream) {
            fprintf($stream, self::CURSOR_SHOW);
        });
        return $this;
    }

    /**
     * Расчитаем теущий процент и отдадим отформатированную строку
     * @return string
     */
    private function percentString()
    {
        $percent = number_format(($this->current * 100) / $this->max, 2);
        return str_pad($percent, 5, ' ', STR_PAD_LEFT) . '%';
    }

    /**
     * Получим примерное время выполнения
     * @return string
     */
    private function getETA()
    {
        if (count($this->advancement) == 1) {
            return '--:--:--';
        }
        $timeForCurrent = $this->advancement[$this->current];
        $initialTime = $this->advancement[0];
        $seconds = ($timeForCurrent - $initialTime);
        $percent = ($this->current * 100) / $this->max;
        $estimatedSecondsToEnd = intval($seconds * 100 / $percent) - $seconds;
        return date('H:i:s', mktime(0, 0, $estimatedSecondsToEnd));
    }

    /**
     * Получим строку bar
     * @param $buffer
     * @return string
     */
    private function getBar($buffer)
    {
        $buffer = str_replace('#bar#', '', $buffer);
        $lengthAvailable = $this->width - mb_strlen($buffer, self::ENCODING);
        $barArray = array_fill(0, $lengthAvailable, $this->remainingBarChar);
        $position = intval(($this->current * $lengthAvailable) / $this->max);
        for ($i = $position; $i >= 0; --$i) {
            $barArray[$i] = $this->doneBarChar;
        }
        $barArray[$position] = ($position == $lengthAvailable) ? $this->doneBarChar : $this->currentPosChar;
        return implode('', $barArray);
    }

    /**
     * Сброс progress bar для нового отсчета
     */
    private function reset()
    {
        $this->current = 0;
        $this->advancement = [$this->current => time()];
        return $this;
    }
}