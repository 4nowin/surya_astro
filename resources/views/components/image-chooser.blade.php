<div class="position-relative bg-secondary text-end {{ $attributes['class'] }} rounded overflow-hidden"
    style="height: {{ $attributes['height'] }}; width:{{ $attributes['width'] }}; background-size:cover; background-position:center; background-image:url('{{ url(isset($attributes['value']) ? $attributes['value'] : '/images/placeholder.png') }}')"
    id="{{ $id }}-preview">
    <input type="text" name="{{ $attributes['name'] }}" id="{{ $id }}-field" class="d-none"
        value="{{ $attributes['value'] ?? '' }}" />



    <label for="{{ $id }}-input" class="position-absolute" style="top: 5px; right: 5px">
        <a class="d-inline-block btn btn-light text-primary edit-btn">
            <i class="fas fa-camera"></i>
            <span>Edit Picture</span>
        </a>
        <input type="file" id="{{ $id }}-input" class="d-none" />
    </label>
    {{ $slot }}

    <div id="{{ $id }}-progress" class="position-absolute bottom-0 start-0 bg-success"
        style="height: 4px; transition: width 0.25s"></div>
</div>
<style>
    .edit-btn{
        padding: 2px 6px;
        height: 29px;
        width: 29px;
        transition: 0.25s ease-in;
        overflow: hidden;
        white-space: nowrap;
    }
    .edit-btn:hover{
        width: 120px;
    }
    .edit-btn span{ 
        color: transparent;
        transition: 0.25s ease-in;
    }
    .edit-btn:hover span{
        color: inherit
    }
</style>
<script>
    $(function() {
        const _id = "{{ $id }}";

        const _elm = document.querySelector("#{{ $id }}-input");
        const _preview = document.querySelector("#{{ $id }}-preview");
        const _field = document.querySelector("#{{ $id }}-field");
        const _progress_div = document.querySelector("#{{ $id }}-progress");
        _elm.onchange = (_event) => {
            const files = _event.target.files;
            const url = URL.createObjectURL(files[0]);

            ask("Are you sure?").then(() => {
                var form = new FormData();
                form.append("upload", files[0]);
                form.append('dir', '/');
                axios.post('/admin/files/uploader', form, {
                    onUploadProgress: _p_event => {
                        const _percentage = (_p_event.loaded / _p_event.total) * 100;
                        _progress_div.style.width = _percentage + "%";
                    }
                }).then(res => {
                    _preview.style.backgroundImage = `url(${url})`;
                    _field.value = res.data.path;
                }).catch(err => {
                    console.log(res);
                }).finally(e => {
                    _progress_div.style.width = "0px";
                });
            }).catch(e => {

            })

        }
    });
</script>
