@extends('layouts.org-designer')

@section('title', $orgProject->name)

@section('content')
    <livewire:org-designer :org-project="$orgProject" />
@endsection
