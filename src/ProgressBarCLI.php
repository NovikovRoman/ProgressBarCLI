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

    private $width = 80; // ширина строки в символах
    private $max; // максимальное количество checkpoints

    private $current;
    private $advancement;
    private $properties = [
        // формат полосы, по-умолчанию "#current#/#max# [#bar#] #percent# #eta#"
        'format',
        'doneBarChar', // символ завершенной точки, по-умолчанию "="
        'currentPosChar', // символ текущей точки выполнения, по-умолчанию ">"
        'remainingBarChar', // символ следующей точки выполнения, по-умолчанию "-"
    ];
    private $assembledString;

    public function __construct($max, $width = 80)
    {
        $this->max = (int)$max;
        $this->width = (int)$width;
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
        $buffer = $this->dataCollection()->buildString();
        $eolCharacter = "\r";
        if ($isEnd) {
            $pattern = '/(\d{2}:\d{2}:\d{2})/ui';
            if ($stop) { // время красным
                $buffer = preg_replace($pattern, self::COLOR_RED . '$1' . self::COLOR_NORMAL, $buffer);
            } else { // заменим время на complete
                $complete = self::COLOR_GREEN . 'Complete' . self::COLOR_NORMAL;
                $buffer = preg_replace($pattern, $complete, $buffer);
            }
            $this->reset();
            $eolCharacter = "\n";
        }
        echo $buffer . $eolCharacter;
        return $this;
    }

    /**
     * Получим данные для сбора строки
     */
    private function dataCollection()
    {
        $this->assembledString = [
            'current' => $this->current,
            'max' => $this->max,
            'percent' => $this->percentString(),
            'eta' => $this->getETA(),
            'bar' => $this->getBar(),
        ];
        return $this;
    }

    /**
     * Сбор строки
     * @return string
     */
    private function buildString()
    {
        $buffer = $this->format;
        $buffer = str_replace('#current#', $this->assembledString['current'], $buffer);
        $buffer = str_replace('#max#', $this->assembledString['max'], $buffer);
        $buffer = str_replace('#percent#', $this->assembledString['percent'], $buffer);
        $buffer = str_replace('#eta#', $this->assembledString['eta'], $buffer);
        $buffer = str_replace('#bar#', $this->assembledString['bar'], $buffer);
        return str_pad($buffer, $this->width, ' ', STR_PAD_RIGHT);
    }

    /**
     * Скрыть курсор
     * @param resource $stream
     * @return $this
     */
    private function hideCursor($stream = STDOUT)
    {
        fprintf($stream, self::CURSOR_HIDE);
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
            return '--:--:--'; // с пробелом для выравнивания
        }
        $timeForCurrent = $this->advancement[$this->current];
        $initialTime = $this->advancement[0];
        $seconds = ($timeForCurrent - $initialTime);
        $percent = ($this->current * 100) / $this->max;
        $estimatedSecondsToEnd = intval($seconds * 100 / $percent) - $seconds;
        $hoursCount = intval($estimatedSecondsToEnd / 3600);
        $rest = ($estimatedSecondsToEnd % 3600);
        $minutesCount = intval($rest / 60);
        $secondsCount = ($rest % 60);
        return sprintf("%02d:%02d:%02d", $hoursCount, $minutesCount, $secondsCount);
    }

    /**
     * Получим строку bar
     * @return string
     */
    private function getBar()
    {
        $lengthAvailable = $this->width - $this->len();
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

    /**
     * Рассчитать длину сформированной строки
     * @return int
     */
    private function len()
    {
        return mb_strlen(implode(' ', $this->assembledString), self::ENCODING);
    }
}