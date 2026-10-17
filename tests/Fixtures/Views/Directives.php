@use (Waypoint\Html)
@model (array)
@section('title'){{ $model['title'] }}@endsection
<ul>
@foreach ($model['items'] as $item)
    <li @class.first="$item === 'a'">{{ $item }}</li>
@endforeach
</ul>
@if (isset($model['html']))
{{ new Html($model['html']) }}
@endif
