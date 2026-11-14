<?php

declare(strict_types=1);
require __DIR__.'/../sdk/php/vendor/autoload.php';
use Pact\Federation\DelegationState as D;
use Pact\PactError;
function refused(callable $f): void { try { $f(); } catch (PactError $e) { return; } throw new LogicException('Expected rejection'); }
$s = D::start('root', 'a', 100);
$s = D::reserve($s, 'root', 'one', 'd1', 'b', 101);
$s = D::reserve($s, 'one', 'two', 'd2', 'c', 102);
refused(fn () => D::reserve($s, 'two', 'three', 'd3', 'a', 103));
refused(fn () => D::reserve($s, 'two', 'three', 'd3', 'd', 103, true));
refused(fn () => D::step($s, 400));
$s = D::cancel($s, 'one');
$s = D::observe($s, 'two', 'completed');
if ($s['tasks']['two']['status'] !== 'canceled' || $s['tasks']['two']['observations'] !== ['completed']) throw new LogicException('late observation');
refused(fn () => D::reserve($s, 'two', 'three', 'd3', 'd', 103));
$s = D::start('r', 'a', 100);
for ($i = 0; $i < 16; $i++) $s = D::step($s, 101);
refused(fn () => D::step($s, 101));
$s = D::start('r', 'a', 100);
for ($i = 0; $i < 8; $i++) $s = D::reserve($s, 'r', 't'.$i, 'd'.$i, 'm'.$i, 101);
refused(fn () => D::reserve($s, 'r', 't8', 'd8', 'm8', 101));
echo "Federation delegation state passed\n";
