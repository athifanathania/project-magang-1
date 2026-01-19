<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Control - Indomatsumoto</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Animasi Melayang */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-slate-50 font-sans antialiased relative overflow-hidden selection:bg-blue-700 selection:text-white">

    <div class="fixed inset-0 -z-10">
        <div class="absolute top-0 left-0 -translate-x-1/4 -translate-y-1/4 w-[600px] h-[600px] rounded-full bg-sky-400/20 blur-[120px]"></div>
        <div class="absolute bottom-0 right-0 translate-x-1/4 translate-y-1/4 w-[600px] h-[600px] rounded-full bg-blue-500/20 blur-[120px]"></div>
        
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#0000ff0a_1px,transparent_1px),linear-gradient(to_bottom,#0000ff0a_1px,transparent_1px)] bg-[size:24px_24px]"></div>
    </div>
    
    <div class="min-h-screen flex items-center justify-center p-6">
        
        <div 
            class="relative w-full max-w-5xl overflow-hidden rounded-3xl p-10 md:p-14 shadow-2xl ring-1 ring-blue-500/30 animate-float"
            style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white;"
        >
            <div class="absolute top-0 right-0 w-96 h-96 bg-white opacity-10 rounded-full blur-3xl -mr-20 -mt-20"></div>
            <div class="absolute bottom-0 left-0 w-72 h-72 bg-blue-900 opacity-20 rounded-full blur-3xl -ml-20 -mb-20"></div>

            <div class="relative z-10 flex flex-col items-center text-center">
                
                <div class="mb-6 p-4 bg-white/10 rounded-2xl backdrop-blur-md border border-white/20 shadow-lg">
                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                
                <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight mb-2 drop-shadow-md">
                    Document Management Control
                </h1>
                <p class="text-blue-100 text-lg md:text-xl font-medium tracking-wide mb-6">
                    PT. Indomatsumoto Press & Dies Industries
                </p>

                <div class="h-px w-32 bg-gradient-to-r from-transparent via-white/50 to-transparent mb-8"></div>

                <p class="text-sm md:text-base text-blue-50 max-w-2xl mx-auto mb-10 leading-relaxed opacity-90">
                    Sistem terpusat untuk pengelolaan dokumen internal dan dokumen eksternal perusahaan. 
                    Akses mudah untuk publik dan kontrol penuh untuk administrator.
                </p>

                <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto justify-center">
                    
                    <a href="/portal" 
                       class="group relative inline-flex items-center justify-center px-8 py-3.5 text-base font-bold text-white border-2 border-white/30 bg-white/5 rounded-xl transition-all duration-200 hover:bg-white/20 hover:border-white/50 focus:outline-none">
                        <svg class="w-5 h-5 mr-2 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <span>Cari Dokumen Publik</span>
                    </a>

                    <a href="/admin/login" 
                       class="group relative inline-flex items-center justify-center px-8 py-3.5 text-base font-bold text-blue-700 bg-white rounded-xl shadow-lg transition-all duration-200 hover:scale-105 hover:shadow-xl hover:bg-blue-50 focus:outline-none">
                        <span>Login sebagai User</span>
                        <svg class="w-5 h-5 ml-2 transition-transform duration-200 group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                        </svg>
                    </a>

                </div>

                <div class="mt-12 text-xs text-blue-200/60 font-medium">
                    &copy; {{ date('Y') }} PT. Indomatsumoto. Secured Document System.
                </div>
            </div>
        </div>
    </div>

</body>
</html>