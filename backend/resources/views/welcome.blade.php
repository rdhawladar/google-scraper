<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Google Scraper - Efficient Web Data Extraction</title>
        
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        
        <!-- Tailwind CSS -->
        <script src="https://cdn.tailwindcss.com"></script>
        
        <style>
            body {
                font-family: 'Inter', sans-serif;
            }
        </style>
    </head>
    <body class="bg-gray-50">
        <div class="min-h-screen">
            <!-- Navigation -->
            <nav class="bg-white shadow">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex items-center">
                            <span class="text-xl font-bold text-gray-800">Google Scraper</span>
                        </div>
                        <div class="flex items-center space-x-4">
                            <a href="{{ env('FRONTEND_URL') }}" class="text-gray-600 hover:text-gray-900">Frontend</a>
                            <a href="/api/documentation" class="text-gray-600 hover:text-gray-900">API Docs</a>
                            <a href="http://ec2-18-138-248-220.ap-southeast-1.compute.amazonaws.com/" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Try Demo</a>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Hero Section -->
            <main>
                <div class="max-w-7xl mx-auto py-16 px-4 sm:px-6 lg:px-8">
                    <div class="text-center">
                        <h1 class="text-4xl font-extrabold text-gray-900 sm:text-5xl md:text-6xl">
                            Powerful Google Data Extraction
                        </h1>
                        <p class="mt-3 max-w-md mx-auto text-base text-gray-500 sm:text-lg md:mt-5 md:text-xl md:max-w-3xl">
                            Extract search results efficiently with our advanced Google scraping solution. Built with modern technology stack for reliability and speed.
                        </p>
                        <div class="mt-8 flex justify-center">
                            <a href="{{ env('FRONTEND_URL') }}" class="bg-blue-600 text-white px-8 py-3 rounded-lg text-lg font-semibold hover:bg-blue-700 transition-colors">
                                Get Started
                            </a>
                        </div>
                        <!-- Test Credentials -->
                        <div class="mt-6">
                            <p class="text-sm text-gray-600">Test Account:</p>
                            <div class="mt-2 inline-block bg-gray-50 rounded-lg px-4 py-2">
                                <p class="text-sm font-mono">Email: test@example.com</p>
                                <p class="text-sm font-mono">Password: 1234</p>
                            </div>
                        </div>
                    </div>

                </div>
            </main>

            <!-- Footer -->
            <footer class="bg-white">
                <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
                    <div class="text-center text-gray-500">
                        <p>&copy; 2024 Google Scraper. All rights reserved.</p>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
