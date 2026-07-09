@extends('layouts.kasir')

@section('title', 'Pembukuan')
@section('heading', 'Pembukuan')
@section('subheading', 'Ringkasan penjualan lunas')

@section('content')
    @include('shared.sales-report', [
        'filterAction' => route('kasir.pembukuan.index'),
        'pdfUrl' => route('kasir.pembukuan.pdf', $filters),
        'showOrderActions' => true,
    ])
@endsection
