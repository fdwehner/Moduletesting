@extends('layouts.org-designer')

@section('title', $orgProject->name)

@section('content')
    <livewire:org-chart-viewer :org-project="$orgProject" />
@endsection
