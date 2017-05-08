<?php
require_once __DIR__ . '/../vendor/autoload.php';
use ProgressBarCLI\ProgressBarCLI;

echo 'классический' . PHP_EOL;
$pb = new ProgressBarCLI(100);

for ($i = 0; $i < 100; ++$i) {
    $pb->advance();
    usleep(20000);
}

echo 'классический с шагом' . PHP_EOL;
for ($i = 0; $i < 101; $i += 10) {
    $pb->update($i);
    usleep(200000);
}

echo 'произвольное максимальное значение' . PHP_EOL;
$pb = new ProgressBarCLI(1343);
for ($i = 0; $i < 1343; ++$i) {
    $pb->advance();
    usleep(2000);
}

echo 'произвольное максимальное значение с шагом' . PHP_EOL;
for ($i = 0; $i < 1343; $i += 20) {
    $pb->update($i);
    usleep(2000);
}
/**
 * В данном примере необходимо обязательно завершить прогресс, тк в цикле счетчик
 * отсчитает до 1340, что не является завершением прогресса. Из-за этого консольное
 * приглашение будет на текущей строке
 */
$pb->update(1343);

echo 'произвольное максимальное значение с шагом и сбросом' . PHP_EOL;
for ($i = 0; $i < 400; $i += 20) {
    $pb->update($i);
    usleep(200000);
}
/**
 * Либо сбрасываем в случае принудительной остановки
 */
$pb->stop();