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

Alpine.data('randomShopPicker', (shops = [], initialShop = null, fallbackUrl = '/', regions = [], shopMap = {}) => ({
    shops,
    selected: initialShop,
    fallbackUrl,
    regions,
    shopMap,
    selectedCity: '',
    selectedDistrict: '',

    init() {
        if (!this.selected) {
            this.pick();
        }
    },

    cityDistricts() {
        return this.regions.find(region => region.name === this.selectedCity)?.districts || [];
    },

    filteredShops() {
        if (!this.selectedCity) {
            return this.shops;
        }

        const cityData = this.shopMap[this.selectedCity];
        if (!cityData) {
            return [];
        }

        if (!this.selectedDistrict) {
            return cityData._all || [];
        }

        return cityData.districts?.[this.selectedDistrict] || [];
    },

    onCityChange() {
        if (!this.cityDistricts().includes(this.selectedDistrict)) {
            this.selectedDistrict = '';
        }

        this.pick();
    },

    onDistrictChange() {
        this.pick();
    },

    pick() {
        const source = this.filteredShops();
        if (!source.length) {
            this.selected = null;
            return;
        }

        const currentCode = this.selected?.code;
        let candidates = source;

        if (source.length > 1 && currentCode) {
            const filtered = source.filter(shop => shop.code !== currentCode);
            if (filtered.length) {
                candidates = filtered;
            }
        }

        const next = candidates[Math.floor(Math.random() * candidates.length)];
        this.selected = {
            ...next,
            pickedAt: Date.now(),
        };
    },

    selectedUrl() {
        return this.selected?.url || this.fallbackUrl;
    },

    filterLabel() {
        if (this.selectedCity && this.selectedDistrict) {
            return `${this.selectedCity}${this.selectedDistrict}`;
        }

        if (this.selectedCity) {
            return `${this.selectedCity}全部區域`;
        }

        return '全台品牌';
    },

    availableCount() {
        return this.filteredShops().length;
    },
}));

Alpine.data('drinkPicker', (items = []) => ({
    items,
    selected: null,

    pick() {
        if (!this.items.length) return;

        const next = this.items[Math.floor(Math.random() * this.items.length)];
        this.selected = {
            ...next,
            pickedAt: Date.now(),
        };
    },

    hasAnyPrice(item) {
        return !!(item?.price || item?.price_cold || item?.price_hot);
    },
}));

// Nearby stores component
Alpine.data('nearbyStores', (initialBrand = '') => ({
    map: null,
    userLocation: null,
    userMarker: null,
    stores: [],
    filteredStores: [],
    selectedBrand: initialBrand,
    isLoading: true,
    error: null,
    markers: [],
    menuCache: {},
    menuModal: {
        open: false,
        loading: false,
        error: null,
        store: null,
        menu: null,
    },

    async init() {
        await this.loadStores();
        await this.$nextTick();
        this.initMap();
        this.invalidateMapSize();
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

    invalidateMapSize() {
        if (!this.map) return;

        requestAnimationFrame(() => {
            this.map.invalidateSize();
        });
    },

    getUserLocation() {
        if (!navigator.geolocation) {
            this.error = 'Geolocation not supported';
            this.isLoading = false;
            this.invalidateMapSize();
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
                if (this.userMarker) {
                    this.map.removeLayer(this.userMarker);
                }

                this.userMarker = L.marker([this.userLocation.lat, this.userLocation.lng], {
                    icon: L.divIcon({
                        className: 'user-marker',
                        html: '<div class="w-4 h-4 bg-blue-500 rounded-full border-2 border-white shadow-lg"></div>'
                    })
                }).addTo(this.map).bindPopup('你的位置');

                this.updateNearbyStores();
                this.isLoading = false;
                this.invalidateMapSize();
            },
            (error) => {
                console.error('Geolocation error:', error);
                this.error = 'Unable to get your location';
                this.isLoading = false;
                this.invalidateMapSize();
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

        this.fitMapBounds();
    },

    fitMapBounds() {
        if (!this.map || !this.userLocation) return;

        const points = [[this.userLocation.lat, this.userLocation.lng]];
        this.filteredStores.slice(0, 12).forEach(store => {
            points.push([store.lat, store.lng]);
        });

        if (points.length === 1) {
            this.map.setView(points[0], 15);
            return;
        }

        const bounds = L.latLngBounds(points);
        this.map.fitBounds(bounds, {
            padding: [40, 40],
            maxZoom: 16,
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

    async openStoreMenu(store) {
        this.menuModal.open = true;
        this.menuModal.loading = true;
        this.menuModal.error = null;
        this.menuModal.store = store;
        this.menuModal.menu = null;

        if (this.menuCache[store.brand_code]) {
            this.menuModal.menu = this.menuCache[store.brand_code];
            this.menuModal.loading = false;
            return;
        }

        try {
            const response = await fetch(`/api/shops/${store.brand_code}/menu`);
            if (!response.ok) {
                throw new Error('menu fetch failed');
            }

            const data = await response.json();
            this.menuCache[store.brand_code] = data;
            this.menuModal.menu = data;
        } catch (error) {
            console.error('Failed to load menu:', error);
            this.menuModal.error = '目前無法載入這家店的菜單。';
        } finally {
            this.menuModal.loading = false;
        }
    },

    closeStoreMenu() {
        this.menuModal.open = false;
    },

    onBrandChange() {
        this.updateNearbyStores();
        this.invalidateMapSize();
    }
}));

Alpine.start();
