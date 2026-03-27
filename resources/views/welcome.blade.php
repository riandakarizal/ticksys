<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NusaDesk</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <main class="mx-auto flex min-h-screen max-w-4xl items-center justify-center px-6 py-16">
        <div class="panel w-full text-center">
            <p class="text-sm uppercase tracking-[0.3em] text-blue-600">NusaDesk</p>
            <h1 class="mt-3 text-4xl font-black">Helpdesk ticketing system</h1>
            <p class="mt-3 text-slate-600">Halaman default Laravel tidak dipakai. Akses aplikasi melalui halaman login.</p>
            <div class="mt-8">
                <a href="{{ route('login') }}" class="btn-primary">Open Login</a>
            </div>
        </div>
    </main>
</body>
</html>
