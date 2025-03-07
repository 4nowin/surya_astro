<?php

namespace App\View\Components;

use Illuminate\View\Component;

class ImageChooser extends Component
{
    public $id = null;
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($options=[], $selected="", $multiple=false)
    {
        $this->id = "selectize-".rand(111111,999999);
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.image-chooser');
    }
}
