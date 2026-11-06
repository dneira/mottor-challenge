<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($request->is('quotes', 'quotes/*', 'health')) {
            if ($exception instanceof \Illuminate\Validation\ValidationException) {
                return response()->json(['message' => 'Los datos enviados no son válidos.', 'errors' => $exception->errors()], 422);
            }
            $http = $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
            $status = $http ? $exception->getStatusCode() : 500;
            $message = $status === 404 ? 'Presupuesto no encontrado.' : ($status >= 500 ? 'Error interno del servidor.' : 'Solicitud no permitida.');

            return response()->json(['message' => $message], $status, $http ? $exception->getHeaders() : []);
        }

        return parent::render($request, $exception);
    }
}
