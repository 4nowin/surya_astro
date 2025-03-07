<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Selectize extends Component
{
    public $options = [];
    public $multiple = false;
    public $selected = []||"";
    public $hasImage = false;
    public $id = null;
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($options=[], $selected="", $multiple=false)
    {
        $this->options = $options;
        $this->selected = $selected;
        $this->multiple = $multiple;

        if(count($options) > 0 && is_array($options[0])){
            $this->hasImage = true;
        }
        $this->id = "selectize-".rand(111111,999999);
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.selectize');
    }
}
