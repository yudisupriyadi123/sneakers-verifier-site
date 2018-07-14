@extends('layouts.app')

@section('title', 'Review result: #'.$verification_item->id)

@section('content')
    <div id="review-result">
        <h3>Hasil Review</h3>
        <p>
            @if ($verification_item->review->status == 'Asli')
            Reviewer menyatakan bahwa Sneakers ini <b>ASLI</b>
            @endif
            @if ($verification_item->review->status == 'Tidak Asli')
            Reviewer menyatakan bahwa Sneakers ini <b>PALSU</b>
            @endif
            @if ($verification_item->review->status == 'Review Ditolak')
            :( Reviewer menolak verifikasi ini (lihat pesan reviewer untuk selengkapnya.)
            @endif
        </p>
        <p>
            Pesan dari reviewer:<br>
            {{ $verification_item->review->note OR 'tidak ada pesan' }}
        </p>
    </div>

    <h3>Informasi Verification Item</h3>
    @if ($verification_item->type == 'Gambar')
    Berikut gambar foto sneakers terupload Anda:
    @foreach ($verification_item->verification_item_images as $verification_item_image)
    <img src="{{ asset('storage/verification_sneakers_images/'.$verification_item_image->path) }}" alt="Sneakers Image" width="150" height="120">
    @endforeach
    <div id="verification-info">
        Tanggal upload: {{ $verification_item->created_at }}
        Status review: {{ $verification_item->status_review }}
    </div>
    @endif

    @if ($verification_item->type == 'Link')
    <div class="verification-info">
        Link: {{ $verification_item->verification_item_link->link  }}   <br>
        Tanggal submit: {{ $verification_item->created_at }}            <br>
        Status review: {{ $verification_item->status_review }}
    </div>
    @endif
@endsection
