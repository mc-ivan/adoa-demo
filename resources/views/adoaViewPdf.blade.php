@extends('layouts.layout')

@section('sidebar')
    @include('layouts.sidebar', ['sidebar'=> Menu::get('sidebar_request')])
@endsection
@section('css')
    <link rel="stylesheet" href="{{mix('/css/package.css', 'vendor/processmaker/packages/adoa')}}">
@endsection
@section('content')
<div class="container page-content">
    @if($pdf)
        <h3>Request {{$pdf->request_id}}</h3>
        <div class="card card-body table-card" id="pdf">
            <embed src="/adoa/view/{{$pdf->request_id}}/{{$pdf->file_id}}" frameborder="0" width="100%" height="800px">
        </div>
    @else
        <script type="text/javascript">
            window.location.replace('/adoa/dashboard/todo');
        </script>
    @endif
</div>
@section('js')
@endsection
@endsection
