<?php global $path; ?>
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>

<style>
    #installer-app .badge {
        display: inline-block;
        width: 40px;
        height: 20px;
        border-radius: 5px;
        margin-right: 5px;
    }
</style>

<div id="installer-app">
    <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container" style="max-width:1200px;">
            <button class="btn btn-primary" style="float:right" v-click="add_manufacturer">+ Add Installer</button>
            <h2>Installers</h2>
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
                    <th>Color</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="installer in sortedInstallers" :key="installer.id" v-if="installer.name">
                    <td>{{ installer.name }}</td>
                    <td>
                        <a v-if="installer.url" :href="installer.url" target="_blank">{{ installer.url }}</a>
                        <span v-else>-</span>
                    </td>
                    <td>
                        <a v-if="installer.logo" :href='installer.url'><img class='logo' :src="path+'theme/img/installers/'+installer.logo"/></a>
                        <span v-else>-</span>
                    </td>
                    <td>{{ installer.systems }}</td>
                    <td>
                        <div class="badge" :style="{backgroundColor: installer.color, color: '#fff'}"></div>
                    </td>
                </tr>
            </tbody>
        </table>

    </div>
</div>

<script>
    new Vue({
        el: '#installer-app',
        data: {
            path: "<?php echo $path; ?>",
            installers: []
        },
        methods: {
            add_manufacturer() {
                // Placeholder for adding a new installer
                // This could open a modal or redirect to a form page
                alert('Add Installer functionality not implemented yet.');
            }
        },
        computed: {
            sortedInstallers() {
                return this.installers.slice().sort((a, b) => b.systems - a.systems);
            }
        },
        mounted() {
            axios.get('list.json')
                .then(res => {
                    this.installers = res.data;
                });
        }
    });
</script>