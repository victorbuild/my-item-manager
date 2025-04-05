import {createRouter, createWebHistory} from 'vue-router'
import ItemManager from './Pages/ItemManager.vue'
import CreateItem from './Pages/CreateItem.vue'
import ItemView from './Pages/ItemView.vue'
import EditItem from './Pages/EditItem.vue'

const routes = [
    {
        path: '/',
        name: 'ItemList',
        component: ItemManager
    },
    {path: '/create', component: CreateItem},
    {path: '/items/:id', component: ItemView},
    { path: '/edit/:id', component: EditItem },
]

export default createRouter({
    history: createWebHistory(),
    routes,
})
