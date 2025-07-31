<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Scripts -->
    <!-- <script src="{{ asset('/js/admin/admin.js') }}" defer></script> -->

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

    <!-- Styles -->
    <!-- <link href="{{ asset('/css/admin/admin.css') }}" rel="stylesheet"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" integrity="sha512-xh6O/CkQoPOWDdYTDqeRdPCVd1SpvCA9XXcUnZS2FmJNp1coAFzvtCN9BmamE+4aHK8yyUHUSCcJHgXloTyT2A==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <!-- <script src="https://unpkg.com/axios/dist/axios.min.js"></script> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" integrity="sha512-xh6O/CkQoPOWDdYTDqeRdPCVd1SpvCA9XXcUnZS2FmJNp1coAFzvtCN9BmamE+4aHK8yyUHUSCcJHgXloTyT2A==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js" integrity="sha256-+8RZJua0aEWg+QVVKg4LEzEEm/8RFez5Tb4JBNiV5xA=" crossorigin="anonymous"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.13.6/js/standalone/selectize.min.js" integrity="sha512-pgmLgtHvorzxpKra2mmibwH/RDAVMlOuqU98ZjnyZrOZxgAR8hwL8A02hQFWEK25V40/9yPYb/Zc+kyWMplgaA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.13.6/css/selectize.bootstrap5.min.css" integrity="sha512-w4sRMMxzHUVAyYk5ozDG+OAyOJqWAA+9sySOBWxiltj63A8co6YMESLeucKwQ5Sv7G4wycDPOmlHxkOhPW7LRg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="{{ asset('build/assets/admin_app-v1.0.0.css') }}">
    <script type="module" src="{{ asset('build/assets/admin_app-v1.0.0.js') }}"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}" />

</head>

<body class="">
    <div id="preloader" class="position-fixed align-items-center justify-content-center py-1 px-3 shadow-sm" style="background-color: #f8facf;  backdrop-filter: blur(20px); z-index: 999999999; top: 80px; left: 50%; transform:translateX(-50%); display: none">
        Please wait while loading...
    </div>
    <div class="layout has-sidebar fixed-sidebar fixed-header">
        @if (Auth::guard('admin')->check())
        @include('admin.layouts.partials.navbar')
        @endif

        <div class="position-fixed shadow-sm" style="background-color: #434343; left:42%; border-radius: 2%; backdrop-filter: blur(20px); z-index: 999999999;">
            @yield('subnav')
        </div>

        <div id="overlay" class="overlay"></div>
        <div class="layout">
            <main class="content">

                <a id="btn-toggle" href="#" class="sidebar-toggler break-point-lg">
                    <i class="fa-solid fa-bars"></i>
                </a>
                <div class="container-lg py-4">

                    @yield('content')

                    <footer class="footer" style="position: fixed; left: 0; bottom: 0; width: 100%; text-align: center; z-index: -1;">
                        <small style="margin-bottom: 20px; display: inline-block">
                            © 2023 made with
                            <span style="color: red; font-size: 18px">&#10084;</span> by -
                            <a href="https://www.cubebitz.com">Cube Bitz</a>
                        </small>
                        <br />
                        <div class="social-links">
                            <a href="https://github.com/azouaoui-med" target="_blank">
                                <i class="ri-github-fill ri-xl"></i>
                            </a>
                            <a href="https://twitter.com/azouaoui_med" target="_blank">
                                <i class="ri-twitter-fill ri-xl"></i>
                            </a>
                            <a href="https://codepen.io/azouaoui-med" target="_blank">
                                <i class="ri-codepen-fill ri-xl"></i>
                            </a>
                            <a href="https://www.linkedin.com/in/mohamed-azouaoui/" target="_blank">
                                <i class="ri-linkedin-box-fill ri-xl"></i>
                            </a>
                        </div>
                    </footer>

                </div>
            </main>
            <div class="overlay"></div>
        </div>

    </div>
    @section('scripts')

    @show

    <div class="modal fade" id="ask-modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="title">Confirm</h5>
                </div>
                <div class="modal-body">
                    <p id="message"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" id="cancel-btn">NO</button>
                    <button type="button" class="btn btn-sm btn-primary" id="confirm-btn">YES</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cropper-modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header mb-0">
                    <h5 class="modal-title" id="title">Confirm</h5>
                </div>
                <div class="modal-body p-0 mt-0">
                    <div id="preview" style="width: 100%; padding-top:100%; background-size:cover; background-position:center"></div>
                </div>


                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" id="cancel-btn">NO</button>
                    <button type="button" class="btn btn-sm btn-primary" id="confirm-btn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    @if (Auth::user() && Auth::user()->can('retreat:create'))
    <div class="modal fade" id="new-retreat-modal" tabindex="-1">
        <form enctype="multipart/form-data" method="post" action="/admin/retreat">
            @csrf
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="title">Create Retreat</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="name-input">Title</label>
                            <input type="text" class="form-control" id="name-input" placeholder="Enter retreat title" name="title">
                        </div>

                        <div class="form-group mt-3">
                            <label for="overview-input">Overview</label>
                            <x-rich-text-editor name="overview" required="required" placeholder="Enter retreat overview"></x-rich-text-editor>
                        </div>

                        <div class="row mb-3">
                            <div class="col">
                                <div class="form-group mt-3">
                                    <label for="start-date-input">Start Date</label>
                                    <input id="start-date-input" type="date" class="form-control" placeholder='Enter Start Date here' name="start_date" required="">

                                </div>
                            </div>
                            <div class="col">
                                <div class="form-group mt-3">
                                    <label for="end-date-input">End Date</label>
                                    <input id="end-date-input" type="date" class="form-control" placeholder='Enter End Date here' name="end_date" required="">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                    </div>
                </div>
            </div>
        </form>
    </div>


    <div class="modal fade" tabindex="-1" id="form-modal">
        <div class="modal-dialog">

        </div>
    </div>

    @endif
   

    @if (session('status'))
    <div class="alert alert-success">
        {{ session('status') }}
    </div>
    @endif

    

    <!-- Notification Modal -->
<div class="modal fade" id="poojaNotifyModal" tabindex="-1" aria-labelledby="notifyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="notifyForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send Pooja Notification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <div class="mb-3">
                        <label for="notify-title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="notify-title" name="title" required>
                    </div>

                    <div class="mb-3">
                        <label for="notify-description" class="form-label">Description</label>
                        <textarea class="form-control" id="notify-description" name="description" rows="3" required></textarea>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Send Notification</button>
                </div>
            </div>
        </form>
    </div>
</div>


<script>
    const modal = document.getElementById('poojaNotifyModal');
    const titleInput = document.getElementById('notify-title');
    const descInput = document.getElementById('notify-description');
    const form = document.getElementById('notifyForm');

    const lang = "{{ Config::get('app.locale') ?? 'en' }}";

    modal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const title = button.getAttribute('data-title');
        const excerpt = button.getAttribute('data-excerpt');

        titleInput.value = title;
        descInput.value = excerpt;
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const payload = {
            title: titleInput.value,
            description: descInput.value
        };

        fetch(`/api/${lang}/pooja-notification`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                alert("Notification sent!");
                const modalInstance = bootstrap.Modal.getInstance(modal);
                modalInstance.hide();
            })
            .catch(error => {
                console.error('Notification Error:', error);
                alert("Failed to send notification.");
            });
    });
</script>

</body>
<script src="https://cdn.ckeditor.com/ckeditor5/35.1.0/classic/ckeditor.js"></script>
<script>
    class CKUploadAdapter {
        constructor(loader) {
            // The file loader instance to use during the upload.
            this.loader = loader;
        }


        // Starts the upload process.
        upload() {
            return this.loader.file
                .then(file => new Promise((resolve, reject) => {
                    this._initRequest();
                    this._initListeners(resolve, reject, file);
                    this._sendRequest(file);
                }));
        }

        // Aborts the upload process.
        abort() {
            // Reject the promise returned from the upload() method.
            server.abortUpload();
        }

        // Initializes the XMLHttpRequest object using the URL passed to the constructor.
        _initRequest() {
            const xhr = this.xhr = new XMLHttpRequest();

            // Note that your request may look different. It is up to you and your editor
            // integration to choose the right communication channel. This example uses
            // a POST request with JSON as a data structure but your configuration
            // could be different.
            xhr.open('POST', '{{ url(' / admin / files / ck - upload ') }}', true);
            xhr.setRequestHeader('X-CSRF-TOKEN', $('meta[name="csrf-token"]').attr('content'));
            xhr.setRequestHeader('X-Requested-With', "XMLHttpRequest");
            xhr.responseType = 'json';
        }
        // Initializes XMLHttpRequest listeners.
        _initListeners(resolve, reject, file) {
            const xhr = this.xhr;
            const loader = this.loader;
            const genericErrorText = `Couldn't upload file: ${ file.name }.`;

            xhr.addEventListener('error', () => reject(genericErrorText));
            xhr.addEventListener('abort', () => reject());
            xhr.addEventListener('load', () => {
                const response = xhr.response;

                // This example assumes the XHR server's "response" object will come with
                // an "error" which has its own "message" that can be passed to reject()
                // in the upload promise.
                //
                // Your integration may handle upload errors in a different way so make sure
                // it is done properly. The reject() function must be called when the upload fails.
                if (!response || response.error) {
                    return reject(response && response.error ? response.error.message : genericErrorText);
                }

                // If the upload is successful, resolve the upload promise with an object containing
                // at least the "default" URL, pointing to the image on the server.
                // This URL will be used to display the image in the content. Learn more in the
                // UploadAdapter#upload documentation.
                resolve({
                    default: response.url
                });
            });

            // Upload progress when it is supported. The file loader has the #uploadTotal and #uploaded
            // properties which are used e.g. to display the upload progress bar in the editor
            // user interface.
            if (xhr.upload) {
                xhr.upload.addEventListener('progress', evt => {
                    if (evt.lengthComputable) {
                        loader.uploadTotal = evt.total;
                        loader.uploaded = evt.loaded;
                    }
                });
            }
        }
        // Prepares the data and sends the request.
        _sendRequest(file) {
            // Prepare the form data.
            const data = new FormData();

            data.append('upload', file);

            // Important note: This is the right place to implement security mechanisms
            // like authentication and CSRF protection. For instance, you can use
            // XMLHttpRequest.setRequestHeader() to set the request headers containing
            // the CSRF token generated earlier by your application.

            // Send the request.
            this.xhr.send(data);
        }
    }

    function CKUploadAdapterPlugin(editor) {
        editor.plugins.get('FileRepository').createUploadAdapter = (loader) => {
            // Configure the URL to the upload script in your back-end here!
            return new CKUploadAdapter(loader);
        };
    }
</script>
<script>
    $(document).ready(() => {
        //
        function ___postAjax() {
            document.querySelectorAll("input[type=datetime]:not(.flatpickr-input)").forEach(_elm => {
                flatpickr(_elm, {
                    enableTime: true,
                });
            });
        }
        //jquery 
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $(document).ajaxStart(() => {
            $("#preloader").fadeIn();
        })
        $(document).ajaxComplete(() => {
            $("#preloader").fadeOut();
            ___postAjax();
        });

        //axios
        axios.defaults.headers.common['X-CSRF-TOKEN'] = $('meta[name="csrf-token"]').attr('content');
        axios.interceptors.request.use(function(config) {
            $("#preloader").fadeIn();
            return config;
        }, function(error) {
            $("#preloader").fadeOut();
            return Promise.reject(error);
        });
        axios.interceptors.response.use(function(response) {
            $("#preloader").fadeOut();
            return response;
        }, function(error) {
            $("#preloader").fadeOut();
            return Promise.reject(error);
        });


        //money
        const ask_modal_container = document.getElementById('ask-modal');
        const ask_modal = new bootstrap.Modal(ask_modal_container, {
            backdrop: 'static',
            keyboard: false,
        });
        window.ask = async (message) => {
            return new Promise((resolve, reject) => {
                ask_modal_container.querySelector("#message").innerHTML = message;
                ask_modal_container.querySelector("#confirm-btn").onclick = () => {
                    ask_modal.hide();
                    resolve(true);
                }
                ask_modal_container.querySelector("#cancel-btn").onclick = () => {
                    ask_modal.hide();
                    reject(false);
                }
                ask_modal.show();
            });
        }


        //flatpicker 
        ___postAjax();

        $("[inline-edit-table]").each(function() {



            $(this).change((_evt) => {
                window.ask("Are you sure?").then(() => {
                    const table = $(this).attr("inline-edit-table");
                    const field = $(this).attr("inline-edit-field");
                    const where = $(this).attr("inline-edit-where");
                    const value = $(this).val();
                    axios.post("{{ url('/admin/settings/inline-edit') }}", {
                        table,
                        field,
                        value,
                        where
                    }).then(console.log).catch(console.error)
                }).catch(err => {

                })
            })
        });

    });
</script>


</html>