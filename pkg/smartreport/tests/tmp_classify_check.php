<?php
// Throwaway harness: verifies classifyDirection() against the numbers the
// customer reported as wrongly internal. Not shipped.

error_reporting(E_ALL & ~E_DEPRECATED);

define('SMR_CONFIG', __DIR__ . '/../config');
define('SMR_BASE_URL', '/smartreport');

require __DIR__ . '/../app/core/Config.php';
require __DIR__ . '/../app/features/calls/models/CdrModel.php';
require __DIR__ . '/../app/features/calls/services/ReferenceRepository.php';

// Stub: extensions 100-109 (+104/105/107) internal, nothing else.
class RefStub extends SmartReport\Features\Calls\Services\ReferenceRepository
{
    private $stubInternal = ['100' => true, '101' => true, '102' => true, '103' => true, '104' => true, '105' => true, '106' => true, '107' => true, '108' => true, '109' => true];

    public function isInternal($number)
    {
        $key = SmartReport\Features\Calls\Models\CdrModel::digits($number);
        return $key !== '' && isset($this->stubInternal[$key]);
    }

    public function isTrunkChannel($channel)
    {
        return strpos((string) $channel, 'SIP/') === 0 || strpos((string) $channel, 'PJSIP/') === 0;
    }

    public function isDid($number)
    {
        return false;
    }
}

$model = (new ReflectionClass('SmartReport\Features\Calls\Models\CdrModel'))->newInstanceWithoutConstructor();
$rm = new ReflectionMethod($model, 'classifyDirection');
$rm->setAccessible(true);

$ref = new RefStub();

function entry($src, $dst, $ctx, $channel = 'SIP/104-00001')
{
    return ['src' => $src, 'dst' => $dst, 'dcontext' => $ctx, 'channel' => $channel];
}

$cases = [
    // Customer-reported wrong-internal instances
    ['105 -> 11577 (trunk peer)',      entry('105', '11577', 'from-internal'), 'out'],
    ['105 -> 21577 (trunk peer)',      entry('105', '21577', 'from-internal'), 'out'],
    ['105 -> 109013570206 (external)', entry('105', '109013570206', 'from-internal'), 'out'],
    ['105 -> 109033439363 (Persian)',  entry('105', '109۰۳۳۴۳۹۳۶۳', 'from-internal'), 'out'],
    // True internal calls must stay internal
    ['105 -> 104 (real internal)',     entry('105', '104', 'from-internal'), 'int'],
    ['107 -> 105 (real internal)',     entry('107', '105', 'from-internal'), 'int'],
    // Outbound to full external number
    ['105 -> 138211191 (external)',    entry('105', '138211191', 'from-internal'), 'out'],
    ['105 -> 9153940531 (mobile)',     entry('105', '9153940531', 'from-internal'), 'out'],
    // Inbound contexts untouched
    ['PSTN -> 300 (from-pstn)',        entry('9153940531', '300', 'from-pstn', 'SIP/trunk1-abc'), 'in'],
    ['trunk -> queue (ext-queues)',    entry('9153940531', '2000', 'ext-queues', 'SIP/trunk1-abc'), 'in'],
];

$fail = 0;
foreach ($cases as $case) {
    list($name, $entry, $want) = $case;
    $got = $rm->invoke($model, $ref, $entry, [$entry]);
    $ok = $got === $want;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . ' => ' . $got . ' (want ' . $want . ')' . PHP_EOL;
}

echo $fail === 0 ? "\nALL PASS\n" : "\n{$fail} FAILURES\n";
exit($fail === 0 ? 0 : 1);
