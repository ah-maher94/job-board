@if($errors->any())
<div class="mb-4 p-4 bg-red-200 text-red-800">
    <ul>
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif