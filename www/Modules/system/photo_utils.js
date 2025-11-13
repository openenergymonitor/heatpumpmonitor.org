/**
 * Photo Utilities
 * Shared utility functions for photo handling across components
 */
var PhotoUtils = {
    // Placeholder image for uploading photos (simple gray rectangle with text)
    PLACEHOLDER_IMAGE: 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent('<svg width="150" height="150" xmlns="http://www.w3.org/2000/svg"><rect width="150" height="150" fill="#f5f5f5" stroke="#ddd" stroke-width="2" stroke-dasharray="5,5"/><text x="75" y="75" text-anchor="middle" font-family="Arial, sans-serif" font-size="12" fill="#999">Uploading...</text></svg>'),

    /**
     * Select the best thumbnail size for display
     * @param {Object} photo - Photo object with thumbnails array
     * @param {string|number} desired_size - Desired size (e.g., '150', '80x60', 150)
     * @param {string} basePath - Base path to prepend to URLs
     * @returns {string} - URL of best matching thumbnail or original image
     */
    selectThumbnail: function(photo, desired_size = '150', basePath = '') {
        // Handle uploading photos that don't have server URLs yet
        if (photo.uploading || (!photo.url && !photo.thumbnails)) {
            // Use preview if available (base64 from FileReader), otherwise use placeholder
            return photo.preview || this.PLACEHOLDER_IMAGE;
        }

        // If photo doesn't have thumbnails, use original
        if (!photo.thumbnails || !Array.isArray(photo.thumbnails) || photo.thumbnails.length === 0) {
            if (!photo.url) {
                return this.PLACEHOLDER_IMAGE;
            }
            return basePath + photo.url;
        }

        // Initialize to default values
        let desired_width = 150, desired_height_final = 150;
        // Handle different input formats
        if (typeof desired_size === 'string') {
            if (desired_size.includes('x')) {
                // Format like '80x60'
                const parts = desired_size.split('x');
                const w = parseInt(parts[0]);
                const h = parseInt(parts[1]);
                if (!isNaN(w) && !isNaN(h)) {
                    desired_width = w;
                    desired_height_final = h;
                }
            } else {
                // Format like '150' (square)
                const s = parseInt(desired_size);
                if (!isNaN(s)) {
                    desired_width = desired_height_final = s;
                }
            }
        } else if (typeof desired_size === 'number') {
            if (!isNaN(desired_size) && desired_size > 0) {
                desired_width = desired_size;
                desired_height_final = desired_size; // Square
            }
        }

        // Try to find exact match by dimensions
        const exact_match = photo.thumbnails.find(thumb => 
            thumb.width === desired_width && thumb.height === desired_height_final
        );
        if (exact_match) {
            return basePath + exact_match.url;
        }

        // Find best fit by area (closest to desired dimensions)
        const desired_area = desired_width * desired_height_final;
        let best_match = null;
        let smallest_diff = Infinity;

        photo.thumbnails.forEach(thumb => {
            if (thumb.width && thumb.height) {
                const thumb_area = thumb.width * thumb.height;
                const area_diff = Math.abs(thumb_area - desired_area);
                
                if (area_diff < smallest_diff) {
                    smallest_diff = area_diff;
                    best_match = thumb;
                }
            }
        });

        if (best_match) {
            return basePath + best_match.url;
        }

        // Fallback to original image
        if (!photo.url) {
            return this.PLACEHOLDER_IMAGE;
        }
        return basePath + photo.url;
    },

    /**
     * Format file size in human readable format
     * @param {number} bytes - File size in bytes
     * @returns {string} - Formatted file size
     */
    formatFileSize: function(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    /**
     * Format timestamp to readable date
     * @param {number} timestamp - Unix timestamp
     * @returns {string} - Formatted date string
     */
    formatDate: function(timestamp) {
        const date = new Date(timestamp * 1000);
        return date.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    /**
     * Validate file for photo upload
     * @param {File} file - File object to validate
     * @param {Object} options - Validation options
     * @returns {Object} - {valid: boolean, error: string}
     */
    validateFile: function(file, options = {}) {
        const defaults = {
            maxSize: 5 * 1024 * 1024, // 5MB
            allowedTypes: ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']
        };
        const config = Object.assign(defaults, options);

        // Check file type
        if (!config.allowedTypes.includes(file.type)) {
            return {
                valid: false,
                error: `"${file.name}" is not a supported image format. Allowed formats: ${config.allowedTypes.join(', ')}`
            };
        }

        // Check file size
        if (file.size > config.maxSize) {
            const maxSizeMB = Math.round(config.maxSize / (1024 * 1024));
            return {
                valid: false,
                error: `"${file.name}" is too large. Maximum size is ${maxSizeMB}MB.`
            };
        }

        return { valid: true, error: null };
    }
};