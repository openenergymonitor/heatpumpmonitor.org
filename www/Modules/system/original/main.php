<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>

<div id="app">
	<div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
		<div class="container-fluid">
			<h3>Public Systems</h3>
			<p>Here you can see a variety of installations monitored with <a href="https://openenergymonitor.org/">OpenEnergyMonitor</a>, and compare detailed statistic to see how performance can vary.</p>
			<p style="font-style: italic;">Join in with discussion of the results on the forums here: <a href="https://community.openenergymonitor.org/t/introducing-heatpumpmonitor-org-a-public-dashboard-of-heat-pump-performance/21885">Public dashboard of heat pumps</a>.</p>

			<div style="float:right; margin-right:30px">
				<div class="input-group">
					<span class="input-group-text">Stats time period</span>

					<select class="form-control" v-model="stats_time_start" @change="stats_time_start_change" style="width:130px">
						<option value="last30">Last 30 days</option>
						<option value="last365">Last 365 days</option>
						<option v-for="month in available_months_start">{{ month }}</option>
					</select>
					
					<span class="input-group-text" v-if="stats_time_end!='only'">to</span>

					<select class="form-control" v-model="stats_time_end" v-if="stats_time_start!='last30' && stats_time_start!='last365'" @change="stats_time_end_change" style="width:120px">
						<option value="only">Only</option>
						<option v-for="month in available_months_end">{{ month }}</option>
					</select>
				</div>
			</div>

			<div class="input-group" style="width:300px">
				<div class="input-group-text">Filter</div>
				<input class="form-control" name="query" v-model="filterKey">
			</div>



		</div>
	</div>

	<div class="container-fluid mt-3">
		<table width="100%" class="table-hpmon">
			<thead>
				<tr>
					<th class="header">&nbsp;</td>
					<th v-if="!show_kwh_m2" class="header" colspan="6">Property</th>
					<th v-if="show_kwh_m2" class="header" colspan="4">Property</th>
					<th class="header" colspan="4">Heating system</th>
					<th class="header" colspan="6" v-if="show_when_running">Average stats when running</th>
					<th class="header" colspan="3">Annual Performance</th>
					<th class="header">&nbsp;</td>
				</tr>
				<tr>
					<th @click="sort('id', 'asc')" class="center">#</td>
						<!-- Property -->
					<th @click="sort('location', 'asc')">Location</th>
					<th @click="sort('property', 'asc')">Type</th>
					<th v-if="!show_kwh_m2" @click="sort('age', 'desc')">Built</th>
					<th v-if="!show_kwh_m2" @click="sort('floor_area', 'desc')" class="right">Floor Area</th>
					<th v-if="!show_kwh_m2" @click="sort('heat_demand', 'desc')" class="right">Heat Demand</th>
					<th v-if="!show_kwh_m2" @click="sort('insulation', 'asc')">Insulation</th>
					<th v-if="show_kwh_m2" @click="sort('kwh_m2_heat', 'asc')">kWh/m2 heat</th>
					<th v-if="show_kwh_m2" @click="sort('kwh_m2_elec', 'asc')">kWh/m2 elec</th>

					<!-- Heating System -->
					<th @click="sort('hp_model', 'asc')" class="border">Make / Model</th>
					<th @click="sort('hp_output', 'desc')" class="right">Output</th>
					<th @click="sort('hp_type', 'asc')">Source</th>
					<th @click="sort('emitters', 'asc')">Emitters</th>

					<!-- Stats when running -->
					<th @click="sort('standby_kwh', 'desc')" class="border right" v-if="show_when_running">Standby</th>
					<th @click="sort('when_running.elec_W', 'desc')" class="right" v-if="show_when_running">Electric</th>
					<th @click="sort('when_running.heat_W', 'desc')" class="right" v-if="show_when_running">Heat</th>
					<th @click="sort('when_running.flowT', 'desc')" class="center" v-if="show_when_running">Flow</th>
					<th @click="sort('when_running.flow_minus_return', 'desc')" class="center" v-if="show_when_running">Delta</th>
					<th @click="sort('when_running.outsideT', 'desc')" class="center" v-if="show_when_running">Outside</th>

					<!-- Performance -->
					<th @click="sort('elec_kwh', 'desc')" class="border right">Electric</th>
					<th @click="sort('heat_kwh', 'desc')" class="right">Heat</th>
					<th @click="sort('cop', 'desc')" class="center">SCOP</th>
					<th class="nosort border">Charts</th>
				</tr>
			</thead>

			<tbody>
				<template v-for="n in sortedNodes">
					<tr v-bind:id="'row'+n.id" v-bind:class="hiliteClass(n)">
						<!-- Property -->
						<td class="toggle" onclick="toggle(this)" align="right" v-bind:title="'#'+n.id">&plusb;</td>
						<td>{{n.location}} {{isNew(n) ? '&#10024;' : ''}}</td>
						<td>{{n.property}}</td>
						<td v-if="!show_kwh_m2" class="nowrap">{{n.age.replace(' to ', '-').trim()}}</td>
						<td v-if="!show_kwh_m2" class="nowrap" align="right">{{unit_dp(n.floor_area, 'm&sup2;')}}</td>
						<td v-if="!show_kwh_m2" class="nowrap" align="right">{{unit_dp(n.heat_demand, 'kWh')}}</td>
						<td v-if="!show_kwh_m2" v-bind:title="n.insulation"></td>
						<td v-if="show_kwh_m2" class="nowrap" align="right">{{unit_dp(n.kwh_m2_heat, 'kWh/m2',0)}}</td>
						<td v-if="show_kwh_m2" class="nowrap" align="right">{{unit_dp(n.kwh_m2_elec, 'kWh/m2',0)}}</td>

						<!-- Heating System -->
						<td class="border" v-bind:title="n.hp_model">{{n.hp_model.replace(/ \(.*\)/, ' &mldr;')}}</td>
						<td class="nowrap" align="right">{{unit_dp(n.hp_output, 'kW')}}</td>
						<td class="nowrap">{{n.hp_type.split(' ')[0]}}</td>
						<td v-bind:title="n.new_radiators"></td>

						<!-- Stats when running -->
						<td v-bind:class="monthClass(n) + ' nowrap border'" align="right" v-if="show_when_running">
						{{unit_dp(n.standby_kwh, 'kWh', 1)}}</td>
						<td v-bind:class="monthClass(n) + ' nowrap'" align="right" v-if="show_when_running">
						{{unit_dp(n.when_running_elec_kwh, 'kWh', 0)}}</td>
						<td v-bind:class="monthClass(n) + ' nowrap'" align="right" v-if="show_when_running">
						{{unit_dp(n.when_running_heat_kwh, 'kWh', 0)}}</td>
						<td v-bind:class="monthClass(n) + ' nowrap center'" v-if="show_when_running">
						{{unit_dp(n.when_running_flowT, '&#8451;', 0)}}</td>
						<td v-bind:class="monthClass(n) + ' nowrap center'" v-if="show_when_running">
						+{{unit_dp(n.when_running_flow_minus_return, '&#8451;', 1)}}</td>
						<td v-bind:class="monthClass(n) + ' nowrap center'" v-if="show_when_running">
						{{unit_dp(n.when_running_outsideT, '&#8451;', 1)}}</td>

						<!-- Performance -->
						<td v-bind:class="sinceClass(n) + ' nowrap border'" v-bind:title="sinceDate(n)" align="right">
							{{unit_dp(n.elec_kwh, 'kWh')}}
						</td>
						<td v-bind:class="sinceClass(n) + ' nowrap'" v-bind:title="sinceDate(n)" align="right">
							{{unit_dp(n.heat_kwh, 'kWh')}}
						</td>
						<td v-bind:class="sinceClass(n) + ' nowrap center'" v-bind:title="sinceDate(n)">
							{{n.cop > 0 ? n.cop.toFixed(1) : '-'}}
						</td>
						<td class="border"><a v-bind:href="n.url" target="_blank">Link &raquo;</a></td>
					</tr>

					<tr class="extra" style="display: none">
						<td class="extra">&nbsp;</td>
						<td colspan="14" class="extra">
							<b v-if="n.refrigerant">Refrigerant:</b> {{n.refrigerant}}
							<b v-if="n.flow_temp">Flow Temp:</b> {{n.flow_temp}}
							<b v-if="n.buffer">Buffer:</b> {{n.buffer}}
							<b v-if="n.zones">Zones:</b> {{n.zones}}
							<b v-if="n.controls">Controls:</b> {{n.controls}}
							<b v-if="n.freeze">Anti-Freeze:</b> {{n.freeze}}
							<b v-if="n.dhw">DHW:</b> {{n.dhw}}
							<b v-if="n.legionella">Legionella:</b> {{n.legionella}}
							<b v-if="n.notes">Notes:</b> {{n.notes}}
						</td>
					</tr>
				</template>
			</tbody>

			<tfoot>
				<td colspan="1" class="footer"></td>
				<td colspan="6" class="footer">Recently added systems denoted by &#10024;</td>
				<td colspan="4" class="footer"></td>
				<td colspan="3" class="footer"><i>Incomplete data in grey</i></td>
				<td colspan="2" class="footer"></td>
			</tfoot>
		</table>
		<p>Show fabric efficiency in kWh/m2 <input type="checkbox" v-model="show_kwh_m2"> </p>
	</div>
</div>

<script src="<?php echo $path; ?>Modules/system/original/table.js?v=<?php echo time(); ?>"></script>