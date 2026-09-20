<?php

namespace App\Filters;

use App\Libraries\Access;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * يقصر الصفحة على أدوار محددة.
 * الاستخدام في Routes:  ['filter' => RoleFilter::class . ':legal,head']
 * مدير النظام (admin) يمر دائماً.
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! Access::hasAny((array) $arguments)) {
            return service('response')
                ->setStatusCode(403)
                ->setBody(view('errors/forbidden'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
