<!DOCTYPE html>
<html>
<head>
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@mdi/font@6.x/css/materialdesignicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/vuetify@3.x/dist/vuetify.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">
</head>
<body>
    <div id="app">
        <v-layout class="rounded rounded-md">
            <v-app-bar color="surface-variant" title="Application bar"></v-app-bar>

            <v-navigation-drawer>
                <v-list>
                    <v-list-item title="Drawer left"></v-list-item>
                </v-list>
            </v-navigation-drawer>

            <v-navigation-drawer location="right">
                <v-list>
                    <v-list-item title="Drawer right"></v-list-item>
                </v-list>
            </v-navigation-drawer>

            <v-main class="d-flex align-center justify-center" style="min-height: 300px;">
                <v-btn @click="simularPartido()">
                    Simular partido
                </v-btn>
                <!--<p>@{{ partido.equipo1.jugadores }} </p>-->
                <!--<p v-if="partidoSimulado">@{{ partido.equipo1.jugadores }} </p>-->
                <v-table v-if="partidoSimulado">
                    <thead>
                        <tr>
                        <th class="text-left">
                            Name
                        </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                        v-for="item in this.partido.equipo1.jugadores"
                        :key="item.id"
                        >
                        <td>@{{ item.nombre }}</td>
                        </tr>
                    </tbody>
                </v-table>
            </v-main>
        </v-layout>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/vue@2.x/dist/vue.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.js"></script>
    <script>
        new Vue({
            el: '#app',
            vuetify: new Vuetify(),
            data() {
                return {
                    count: 0,
                    partidoSimulado: false,
                    /*partido: {
                        equipo1: {
                            jugadores: [],
                        },
                        equipo2: {
                            jugadores: [],
                        }
                    },*/
                    partido: {
                        minuto: 0,
                        equipo1: {
                            jugadores: [
                                {nombre: '',}
                            ],
                        },
                        equipo2: {},
                    },
                }
            },
            methods: {
                simularPartido: function(){
                    /*axios
                        .get('simularPartido')
                        .then(
                            response => (console.log(response))
                            
                        )*/
                        axios
                            .get('simularPartido', {})
                            .then((response) => {
                                //this.partido = response.data.partido;
                                //console.log(JSON.parse(JSON.stringify(this.partido)));
                                //this.partido = JSON.parse(JSON.stringify(response.data.partido));
                                this.partido.equipo1.jugadores = Object.values(response.data.partido.equipo1.jugadores);
                                console.log(this.partido.equipo1.jugadores.length);
                                //console.log(this.partido[1].jugadores[1].jugador.nombre);
                                //console.log(this.partido.equipo1.jugadores.length)
                                //for (let index = 0; index < response.data.partido.equipo1.jugadores.length; index++) {
                                  //  console.log("hola");
                                //}
                                //console.log(this.partido.equipo1.jugadores.length);
                                //console.log(this.partido);
                                this.partidoSimulado = true;
                            });
                },
            }
        })
    </script>
</body>
</html>
