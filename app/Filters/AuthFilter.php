<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * يمنع فتح أي صفحة محمية بدون تسجيل دخول.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('user_id')) {
            return redirect()->to(site_url('login'))->with('error', 'يرجى تسجيل الدخول أولاً.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // منع حفظ الصفحات السرية في ذاكرة المتصفح
        $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private');

        return $response;
    }
}
