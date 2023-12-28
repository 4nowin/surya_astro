@if (!$hasImage)
    <select id="{{$id}}" {{ $multiple ? 'multiple' : '' }} {{ $attributes }}>
        @foreach ($options as $key => $option)
            <option value="{{ $key }}" {{$selected==$option?"selected":""}}>{{ $option }}</option>
        @endforeach
    </select>
    <script>
        $(function() {
            $("#{{$id}}").selectize({});
        });
    </script>
@endif


@if ($hasImage)
    <select id="{{$id}}" {{ $attributes }}></select>
    <script>
        var options = {!! json_encode($options)!!};
        $('#{{$id}}').selectize({
            maxItems: {{$multiple?10:1}},
            labelField: 'label',
            valueField: 'value',
            plugins: ['remove_button'],
            searchField: ['label', 'value'],
            options: options,
            items: {!! json_encode($multiple? $selected: [$selected]) !!},
            preload: true,
            persist: false,

            render: {
                item: function(item, escape) {
                    return `<div><img src="${escape(item.avater)}" style="width: 20px; height: 20px; border-radius: 25px; object-fit: cover; margin: 2px" /> ${escape(item.label)}</div>`;
                },
                option: function(item, escape) {
                    return `<div><img src="${escape(item.avater)}" style="width: 20px; height: 20px; border-radius: 25px; object-fit: cover; margin: 2px" /> ${escape(item.label)}</div>`;
                }
            },
        });
    </script>
@endif
