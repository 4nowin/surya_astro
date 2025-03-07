<?php

namespace App\View\Components;

use Illuminate\View\Component;

class RichTextEditor extends Component
{
    public $id = null;
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
        $this->id = "rich-text-editor-".rand(1111,9999);

    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {

        return view('components.rich-text-editor');
    }
}
