<?php

namespace SmartReport\Features\Empreport\Controllers;

use SmartReport\Core\App;
use SmartReport\Core\Controller;
use SmartReport\Core\Csrf;
use SmartReport\Features\Calls\Models\CdrModel;
use SmartReport\Features\Empreport\Models\EmployeeModel;
use SmartReport\Services\Audit;
use SmartReport\Services\ExportService;

class EmpReportController extends Controller
{
    public function index()
    {
        $filters = $this->filtersFromRequest();
        $sync = [];
        try {
            $sync = EmployeeModel::deriveSessions($filters);
        } catch (\Exception $e) {
            $sync = ['events' => 0, 'opened' => 0, 'error' => $e->getMessage()];
        }
        $report = EmployeeModel::report($filters);

        $department = trim((string) $this->query('department', ''));
        if ($department !== '') {
            $report = array_values(array_filter($report, function ($row) use ($department) {
                return strcasecmp((string) $row['department'], $department) === 0;
            }));
        }

        // Hourly call distribution across the listed employees' extensions.
        $hourly = ['labels' => [], 'data' => []];
        try {
            $model = new CdrModel();
            $facts = $model->factsInRange($filters);
            $hours = array_fill(0, 24, 0);
            $exts = [];
            foreach ($report as $row) {
                if ($row['extension'] !== '') {
                    $exts[CdrModel::digits($row['extension'])] = true;
                }
            }
            foreach ($facts as $fact) {
                $legsF = isset($fact['legs']) && is_array($fact['legs']) ? $fact['legs'] : [$fact];
                foreach (\SmartReport\Features\Extreport\Models\ExtensionReportModel::extensionsOf($fact, $legsF) as $ext) {
                    if (!isset($exts[$ext])) {
                        continue;
                    }
                    $ts = strtotime((string) (isset($fact['calldate']) ? $fact['calldate'] : ''));
                    if ($ts !== false) {
                        $hours[(int) date('G', $ts)] += (int) (isset($fact['talkTime']) ? $fact['talkTime'] : 0);
                    }
                    break;
                }
            }
            $hourly = [
                'labels' => array_map(function ($h) { return sprintf('%02d:00', $h); }, range(0, 23)),
                'data' => $hours,
            ];
        } catch (\Exception $e) {
            // chart stays empty on CDR failure; page still renders
        }

        $this->view(':features/empreport/views/index', array_merge($filters, [
            'title' => t('empreport.title'),
            'rows' => $report,
            'hourly' => $hourly,
            'sync' => $sync,
            'department' => $department,
            'departments' => $this->departments($report),
            'is_root' => \SmartReport\Core\Auth::role() === 'root',
        ]));
    }

    public function detail()
    {
        $filters = $this->filtersFromRequest();
        $emp = EmployeeModel::find($this->query('id', 0));
        if ($emp === null) {
            $this->redirect('/employee-report', t('empreport.not_found'), 'error');
        }
        $sessions = EmployeeModel::sessionsOf($emp['id'], $filters);

        // Calls of the employee's extension(s) in range.
        $calls = [];
        try {
            $model = new CdrModel();
            $facts = $model->factsInRange($filters);
            $linkedids = [];
            foreach ($facts as $fact) {
                $linkedids[] = isset($fact['linkedid']) ? $fact['linkedid'] : '';
            }
            $legs = $model->legsForFacts($linkedids);
            $model->applyLegs($facts, $legs);

            $recorder = new \SmartReport\Features\Calls\Services\RecordingService();
            $ext = CdrModel::digits(isset($emp['extension']) ? $emp['extension'] : '');
            foreach ($facts as &$fact) {
                $fact['hasRecording'] = !empty($fact['recordingUniqueid']) && $recorder->resolve($fact) !== null;
            }
            unset($fact);

            if ($ext !== '') {
                $calls = \SmartReport\Features\Extreport\Models\ExtensionReportModel::callsOfExtension($facts, $ext);
                $calls = array_slice($calls, 0, 200);
            }
        } catch (\Exception $e) {
            $calls = [];
        }

        $this->view(':features/empreport/views/detail', array_merge($filters, [
            'title' => t('empreport.detail_title'),
            'emp' => $emp,
            'sessions' => $sessions,
            'calls' => $calls,
        ]));
    }

    public function export()
    {
        $filters = $this->filtersFromRequest();
        $report = EmployeeModel::report($filters);
        $headers = ['code', 'name', 'extension', 'department', 'sessions', 'work_seconds', 'calls', 'talk_seconds', 'missed_inbound', 'talk_per_hour'];
        $lines = [];
        foreach ($report as $row) {
            $lines[] = [
                'code' => $row['code'],
                'name' => $row['name'],
                'extension' => $row['extension'],
                'department' => $row['department'],
                'sessions' => $row['sessions'],
                'work_seconds' => $row['work_seconds'],
                'calls' => $row['calls'],
                'talk_seconds' => $row['talk'],
                'missed_inbound' => $row['missed'],
                'talk_per_hour' => $row['talk_per_hour'],
            ];
        }
        Audit::log('empreport_export', 'rows=' . count($lines));
        ExportService::csv('smartreport_employee_report_' . date('Ymd_His') . '.csv', $headers, $lines);
    }

    // ------------------------------------------------- management (root)

    public function manage()
    {
        $this->requireRole(['root']);
        $this->view(':features/empreport/views/manage', [
            'title' => t('empreport.manage_title'),
            'employees' => EmployeeModel::all(),
            'nextCode' => EmployeeModel::nextFreeCode(),
            'codeMin' => EmployeeModel::CODE_MIN,
            'codeMax' => EmployeeModel::CODE_MAX,
        ]);
    }

    public function save()
    {
        $this->requireRole(['root']);
        if (!Csrf::validate()) {
            $this->redirect('/employee-report/manage', t('common.csrf_invalid'), 'error');
        }
        $id = (int) $this->post('id', 0);
        $name = trim((string) $this->post('name', ''));
        $code = CdrModel::digits($this->post('code', ''));
        $extension = CdrModel::digits($this->post('extension', ''));
        $department = trim((string) $this->post('department', ''));
        $active = $this->post('active', '0') === '1' ? 1 : 0;

        if ($name === '') {
            $this->redirect('/employee-report/manage', t('empreport.err_name'), 'error');
        }
        if (!preg_match('/^[0-9]{2,8}$/', $code)) {
            $this->redirect('/employee-report/manage', t('empreport.err_code'), 'error');
        }
        if (EmployeeModel::codeExists($code, $id)) {
            $this->redirect('/employee-report/manage', t('empreport.err_code_taken'), 'error');
        }

        if ($id > 0 && EmployeeModel::find($id) !== null) {
            EmployeeModel::update($id, [
                'name' => $name,
                'code' => $code,
                'extension' => $extension,
                'department' => $department,
                'active' => $active,
            ]);
            Audit::log('employee_updated', 'id=' . $id . ' code=' . $code);
            $this->redirect('/employee-report/manage', t('empreport.saved'));
        }

        EmployeeModel::create([
            'name' => $name,
            'code' => $code,
            'extension' => $extension,
            'department' => $department,
            'active' => $active,
        ]);
        Audit::log('employee_created', 'code=' . $code);
        $this->redirect('/employee-report/manage', t('empreport.saved'));
    }

    public function delete()
    {
        $this->requireRole(['root']);
        if (!Csrf::validate()) {
            $this->redirect('/employee-report/manage', t('common.csrf_invalid'), 'error');
        }
        $id = (int) $this->post('id', 0);
        if (EmployeeModel::find($id) !== null) {
            EmployeeModel::delete($id);
            Audit::log('employee_deleted', 'id=' . $id);
        }
        $this->redirect('/employee-report/manage', t('empreport.deleted'));
    }

    // ------------------------------------------------------------ helpers

    private function departments(array $rows)
    {
        $deps = [];
        foreach ($rows as $row) {
            $d = trim((string) $row['department']);
            if ($d !== '' && !in_array($d, $deps, true)) {
                $deps[] = $d;
            }
        }
        sort($deps);
        return $deps;
    }

    private function filtersFromRequest()
    {
        $today = date('Y-m-d');
        $dateFrom = $this->normalizeDate($this->query('date_from', $today));
        $dateTo = $this->normalizeDate($this->query('date_to', $today));
        if ($dateFrom > $dateTo) {
            set_flash('error', t('calls.invalid_dates'));
            $dateFrom = $today;
            $dateTo = $today;
        }
        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    private function normalizeDate($value)
    {
        $value = trim((string) $value);
        $ts = strtotime($value);
        return $ts === false ? date('Y-m-d') : date('Y-m-d', $ts);
    }
}
