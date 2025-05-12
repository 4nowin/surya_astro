<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;

class AstrologerController extends Controller
{

  public function inquiry()
  {
    $inquiries = Inquiry::orderBy('id', 'DESC')->paginate(10);
    return Inquiry::all();
  }

}
