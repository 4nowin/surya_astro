<?php

namespace App\View\Components\Front;

use App\Models\Event;
use App\Models\Location;
use App\Models\Venue;
use Illuminate\Contracts\Session\Session;
use Illuminate\View\Component;

class Navbar extends Component
{
    //public $type;
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->type = $type;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        $request = request();
        //save location if in params
        if($request->query->has("location")){
            $request->session()->put('location', $request->query("location"));
            $url = url()->current().'?'.http_build_query($request->except("location"));
            print("<script>window.location = \"$url\";</script>");
        }

        $locations = Location::get();
        //fetch locatoin
        $location = null;
        if($request->session()->has("location")){
            $location = Location::where("id", $request->session()->get("location"))->first();
        }

        
        return view('components.front.navbar', compact("locations", "location"));
    }
}
