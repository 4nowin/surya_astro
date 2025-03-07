@extends('admin.layouts.admin')

@section('content')


<div class="container">
    <nav aria-label="breadcrumb mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="?dir=/">Home</a></li>
            @foreach ($links as $link)
            <li class="breadcrumb-item {{app('request')->input('dir')==$link['path']?'active':''}}"><a href="?dir={{$link['path']}}">{{$link["name"]}}</a></li>
            @endforeach
        </ol>
    </nav>
    <div class="mb-4">
    <form action="/admin/files/folder" method="post" class="d-inline-flex">
        @csrf
        <input class="form-control" type="text" name="folder" placeholder="Folder Name"/>
        <input type="hidden" name="dir" value=" {{app('request')->input('dir')}}" />
        <button class="btn btn-primary" type="submit">Create</button>
    </form>

    <form action="/admin/files/file" method="post" enctype="multipart/form-data" class="d-inline-flex">
        @csrf
        <input class="form-control" type="file" name="file" />
        <input type="hidden" name="dir" value=" {{app('request')->input('dir')}}" />
        <button class="btn btn-primary" type="submit">Upload</button>
    </form>
    </div>
    <table class="table table-bordered bg-white table-hover">
        @foreach($results as $result)
        <tr>
            @php
            $href = $result['type']=="dir"?"?dir=".$result["path"]:null;
            @endphp
            <td width="1%">
                <a href="{{$href}}">
                    <img src="{{$result['icon']}}" style="height: 40px; width:40px; object-fit:cover; object-position: center; border-radius:8px"></div>
                </a>
            </td>
            <td>
                {{$result["name"]}}<br/>
                <small>{{$result["mim-type"]}}</small> &bull; <small>{{$result["size"]}} KB</small>
            </td>
            <td width="1%">
                <a><i class="fas fa-trash text-danger"></i></a>
            </td>
        </tr>
        @endforeach
    </table>


</div>

@endsection