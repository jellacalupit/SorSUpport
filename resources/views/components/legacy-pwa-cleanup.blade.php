<script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations()
            .then((registrations) => {
                const sorsupportRegistration = registrations.filter((registration) => {
                    const worker = registration.active || registration.installing || registration.waiting;

                    return worker && new URL(worker.scriptURL).pathname.endsWith('/service-worker.js');
                });

                return Promise.all(sorsupportRegistration.map((registration) => registration.unregister()));
            })
            .then(() => ('caches' in window ? caches.keys() : []))
            .then((cacheNames) => Promise.all(
                cacheNames
                    .filter((cacheName) => cacheName.startsWith('sorsupport-shell-'))
                    .map((cacheName) => caches.delete(cacheName))
            ))
            .catch((error) => {
                console.error('SorSUpport could not remove its previous offline cache.', error);
            });
    }
</script>
