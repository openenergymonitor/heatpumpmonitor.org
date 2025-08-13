<?php global $path; ?>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>

<style>
    #installer-unmatched-app .badge {
        display: inline-block;
        width: 40px;
        height: 20px;
        border-radius: 5px;
        margin-right: 5px;
    }
</style>

<div id="installer-unmatched-app">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container" style="max-width:1200px;">
            <h2>Unmatched Installers</h2>
            <p class="text-muted">These installers appear in system_meta but are not yet added to the installer database.</p>
        </div>
    </div>

    <div class="container" style="max-width:1200px;">
            
        <table class="table mt-3">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>URL</th>
                    <th>Logo</th>
                    <th>Systems</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="installer in unmatchedInstallers" :key="installer.name">
                    <td>{{ installer.name }}</td>
                    <td>
                        <a v-if="installer.url" :href="installer.url" target="_blank" class="text-truncate" style="max-width: 200px; display: inline-block;">{{ installer.url }}</a>
                        <span v-else class="text-muted">-</span>
                    </td>
                    <td>
                        <img v-if="installer.logo" class="logo" :src="path+'theme/img/installers/'+installer.logo" style="max-height: 20px;"/>
                        <span v-else class="text-muted">-</span>
                    </td>
                    <td>{{ installer.systems }}</td>
                </tr>
            </tbody>
        </table>

    </div>
</div>

<script>
    new Vue({
        el: '#installer-unmatched-app',
        data: {
            unmatchedInstallers: []
        },
        methods: {

        },
        mounted() {
            axios.get(path+'installer/unmatched.json')
                .then(res => {
                    this.unmatchedInstallers = res.data;
                })
                .catch(error => {
                    console.error('Error loading unmatched installers:', error);
                });
        }
    });
</script>