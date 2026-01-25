<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            latField: '{{ $getLatField() }}',
            lngField: '{{ $getLngField() }}',
            map: null,
            marker: null,
            lat: null,
            lng: null,
            init() {
                this.lat = parseFloat($wire.get(this.latField)) || 30.0444;
                this.lng = parseFloat($wire.get(this.lngField)) || 31.2357;

                const checkGoogle = setInterval(() => {
                    if (typeof google !== 'undefined') {
                        clearInterval(checkGoogle);
                        this.renderMap();
                    }
                }, 100);

                if (!document.getElementById('google-maps-script')) {
                    const script = document.createElement('script');
                    script.id = 'google-maps-script';
                    script.src = 'https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places';
                    script.async = true;
                    script.defer = true;
                    document.head.appendChild(script);
                }
            },
            renderMap() {
                if (this.map) return;

                const center = { lat: this.lat, lng: this.lng };
                this.map = new google.maps.Map(this.$refs.map, {
                    zoom: 13,
                    center: center,
                    mapTypeId: 'roadmap'
                });

                this.marker = new google.maps.Marker({
                    position: center,
                    map: this.map,
                    draggable: true
                });

                this.marker.addListener('dragend', (event) => {
                    this.lat = event.latLng.lat();
                    this.lng = event.latLng.lng();
                    this.updateWire();
                });

                this.map.addListener('click', (event) => {
                    this.lat = event.latLng.lat();
                    this.lng = event.latLng.lng();
                    this.marker.setPosition(event.latLng);
                    this.updateWire();
                });
            },
            updateWire() {
                $wire.set(this.latField, this.lat);
                $wire.set(this.lngField, this.lng);
            }
        }"
        wire:ignore
    >
        <div x-ref="map" class="w-full h-96 rounded-lg border border-gray-300" style="min-height: 400px;"></div>
    </div>
</x-dynamic-component>
