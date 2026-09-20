<?php

namespace App\Controllers;

use App\Libraries\CaseService;
use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    protected $helpers = ['form', 'url', 'ui'];

    protected CaseService $cases;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->cases = new CaseService();
    }

    /** يحمّل المعاملة ويتحقق من صلاحية الاطلاع عليها */
    protected function loadCase(int $id): array
    {
        $case = $this->cases->find($id);
        if ($case === null || ! $this->cases->canView($case)) {
            throw PageNotFoundException::forPageNotFound('المعاملة غير موجودة أو غير متاحة لك.');
        }

        return $case;
    }

    /** يوقف الطلب برسالة إن لم تتحقق الصلاحية */
    protected function deny(string $message = 'هذا الإجراء غير متاح لك في هذه المرحلة.')
    {
        return redirect()->back()->with('error', $message);
    }

    protected function uid(): int
    {
        return (int) session('user_id');
    }
}
