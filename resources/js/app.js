import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Shop search component
Alpine.data('shopSearch', () => ({
    query: '',
    results: [],
    isLoading: false,
    showDropdown: false,

    async search() {
        if (this.query.length < 1) {
            this.results = [];
            this.showDropdown = false;
            return;
        }

        this.isLoading = true;
        this.showDropdown = true;

        try {
            const response = await fetch(`/api/search?q=${encodeURIComponent(this.query)}`);
            const data = await response.json();
            this.results = data.shops || [];
        } catch (error) {
            console.error('Search error:', error);
            this.results = [];
        } finally {
            this.isLoading = false;
        }
    },

    selectShop(code) {
        window.location.href = `/shop/${code}`;
    },

    closeDropdown() {
        setTimeout(() => {
            this.showDropdown = false;
        }, 200);
    }
}));

// Tag filter component
Alpine.data('tagFilter', () => ({
    activeTag: '',
    shops: [],
    allShops: [],
    tagMapping: {},

    init() {
        this.allShops = JSON.parse(this.$el.dataset.shops || '[]');
        this.tagMapping = JSON.parse(this.$el.dataset.tags || '{}');
        this.shops = this.allShops;
    },

    filterByTag(tag) {
        this.activeTag = tag;

        if (!tag) {
            this.shops = this.allShops;
            return;
        }

        const tagShopCodes = Object.keys(this.tagMapping[tag] || {});
        this.shops = this.allShops.filter(shop => tagShopCodes.includes(shop.code));
    },

    isActive(tag) {
        return this.activeTag === tag;
    }
}));

// Nearby stores component
Alpine.data('nearbyStores', (initialBrand = '') => ({
    map: null,
    userLocation: null,
    stores: [],
    filteredStores: [],
    selectedBrand: initialBrand,
    isLoading: true,
    error: null,
    markers: [],

    async init() {
        await this.loadStores();
        this.initMap();
        this.getUserLocation();
    },

    async loadStores() {
        try {
            const response = await fetch('/api/stores');
            const data = await response.json();
            this.stores = data.stores || [];
        } catch (error) {
            console.error('Failed to load stores:', error);
            this.error = 'Failed to load store data';
        }
    },

    initMap() {
        // Default to Taipei
        this.map = L.map('map').setView([25.0330, 121.5654], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(this.map);
    },

    getUserLocation() {
        if (!navigator.geolocation) {
            this.error = 'Geolocation not supported';
            this.isLoading = false;
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                this.userLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };
                this.map.setView([this.userLocation.lat, this.userLocation.lng], 15);

                // Add user marker
                L.marker([this.userLocation.lat, this.userLocation.lng], {
                    icon: L.divIcon({
                        className: 'user-marker',
                        html: '<div class="w-4 h-4 bg-blue-500 rounded-full border-2 border-white shadow-lg"></div>'
                    })
                }).addTo(this.map).bindPopup('Your location');

                this.updateNearbyStores();
                this.isLoading = false;
            },
            (error) => {
                console.error('Geolocation error:', error);
                this.error = 'Unable to get your location';
                this.isLoading = false;
            }
        );
    },

    updateNearbyStores() {
        if (!this.userLocation) return;

        // Clear existing markers
        this.markers.forEach(marker => this.map.removeLayer(marker));
        this.markers = [];

        // Filter stores by brand if selected
        let storesToShow = this.stores;
        if (this.selectedBrand) {
            storesToShow = this.stores.filter(s => s.brand_code === this.selectedBrand);
        }

        // Calculate distances and sort
        this.filteredStores = storesToShow
            .map(store => ({
                ...store,
                distance: this.calculateDistance(
                    this.userLocation.lat,
                    this.userLocation.lng,
                    store.lat,
                    store.lng
                )
            }))
            .filter(store => store.distance <= 5000) // Within 5km
            .sort((a, b) => a.distance - b.distance)
            .slice(0, 50);

        // Add markers
        this.filteredStores.forEach(store => {
            const marker = L.marker([store.lat, store.lng])
                .addTo(this.map)
                .bindPopup(`<b>${store.name}</b><br>${store.address || ''}`);
            this.markers.push(marker);
        });
    },

    calculateDistance(lat1, lng1, lat2, lng2) {
        const R = 6371000; // Earth's radius in meters
        const dLat = this.toRad(lat2 - lat1);
        const dLng = this.toRad(lng2 - lng1);
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(this.toRad(lat1)) * Math.cos(this.toRad(lat2)) *
                  Math.sin(dLng/2) * Math.sin(dLng/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    },

    toRad(deg) {
        return deg * Math.PI / 180;
    },

    formatDistance(meters) {
        if (meters < 1000) {
            return `${Math.round(meters)}m`;
        }
        return `${(meters / 1000).toFixed(1)}km`;
    },

    openNavigation(store) {
        const url = `https://www.google.com/maps/dir/?api=1&destination=${store.lat},${store.lng}`;
        window.open(url, '_blank');
    },

    onBrandChange() {
        this.updateNearbyStores();
    }
}));

Alpine.start();
