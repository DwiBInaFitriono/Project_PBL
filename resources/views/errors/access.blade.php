<x-auth-layout title="Akses dibatasi" page="access">
    <h1>Akses dibatasi</h1>
    <p class="access-warning" role="alert">{{ $message }}</p>
    <a class="primary-button" href="{{ $destination }}">{{ $destination === '/login' ? 'Masuk kembali' : 'Kembali ke Dashboard' }}</a>
</x-auth-layout>
