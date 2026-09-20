<?php

namespace App\Commands;

use App\Libraries\CaseService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** php spark alerts:check — يُجدول يومياً (Task Scheduler / cron) */
class CheckAlerts extends BaseCommand
{
    protected $group       = 'Investigation';
    protected $name        = 'alerts:check';
    protected $description = 'إنشاء إشعارات الإنذار للمعاملات التي تجاوزت المدد النظامية.';

    public function run(array $params)
    {
        $n = (new CaseService())->generateAlerts();
        CLI::write("تم إنشاء {$n} إشعار إنذار.", 'green');
    }
}
