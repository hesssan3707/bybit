<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApiDocumentationController extends Controller
{
    /**
     * Display the API documentation page
     * This is a public page accessible without authentication
     */
    public function index()
    {
        return view('api-documentation.index');
    }
}
