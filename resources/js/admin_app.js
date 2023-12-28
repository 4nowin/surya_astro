import './bootstrap.bundle.min';
import './admin/navbar';

import './datepicker.min';

import axios from 'axios';
import Crop from 'tinycrop';
import EditorJS from '@editorjs/editorjs';

window.bootstrap = bootstrap;
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

try{
    window.Crop = Crop;
} catch(e){

}

try{
    window.EditorJS =  EditorJS;
} catch(e) {

}

window.money = (amount) => {
    return "₹" + Intl.NumberFormat('en-US').format(amount);
}