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
        if (auth()->check() && auth()->user() && auth()->user()->isWatcher()) {
            return redirect()->route('futures.orders')->with('error', 'کاربر ناظر اجازه دسترسی به این بخش را ندارد.');
        }
        return view('api-documentation.index');
    }
}
