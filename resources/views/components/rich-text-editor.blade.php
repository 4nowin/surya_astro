<div class="position-relative">
    <div id="{{ $id }}-container"></div>
    <textarea name="{{ $attributes['name'] ?? '' }}" id="{{ $id }}-textarea" {{ $attributes }}
        class="position-absolute rich-text-textarea form-control w-100 h-100 top-0 start-0 bg-transparent"
        style="resize: none; z-index:999999; pointer-events: none; color: transparent" required>{{ $slot }}</textarea>
</div>
<style>
    .rich-text-textarea::placeholder {color:transparent}
    :root{
        --ck-border-radius: 6px
    }
</style>

<script>
    $(function() {
        const _node = document.querySelector("#{{ $id }}-container");
        const _elem = document.querySelector("#{{ $id }}-textarea");
        const _editor = ClassicEditor
            .create(_node, {
                extraPlugins: [CKUploadAdapterPlugin],
                placeholder: '{{ $attributes['placeholder'] ?? '' }}'
            })
            .then(editor => {
                function htmlDecode(input) {
                    var doc = new DOMParser().parseFromString(input, "text/html");
                    return doc.documentElement.textContent;
                }
                editor.setData(htmlDecode(_elem.innerHTML));
                editor.model.document.on('change:data', () => {
                    const data = editor.getData();
                    _elem.innerHTML = data;
                });
            })
            .catch(error => {
                console.error(error);
                _elem.style.display = null;
            });
    })
</script>
