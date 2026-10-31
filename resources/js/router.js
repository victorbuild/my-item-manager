import { createRouter, createWebHistory } from 'vue-router'
const Home = () => import('./Pages/Home.vue')
const ItemList = () => import('./Pages/Items/List.vue')
const ItemCreate = () => import('./Pages/Items/Create.vue')
const ItemShow = () => import('./Pages/Items/Show.vue')
const ItemEdit = () => import('./Pages/Items/Edit.vue')
const NotFound = () => import('./Pages/NotFound.vue')
const Login = () => import('./Pages/LoginView.vue')
const Register = () => import('./Pages/RegisterView.vue')
const ProductList = () => import('./Pages/Products/List.vue')
const ProductForm = () => import('./Pages/Products/Form.vue')
const ProductShow = () => import('./Pages/Products/Show.vue')
const DiscardedItemList = () => import('./Pages/DiscardedItems/List.vue')
const ExpiringItemList = () => import('./Pages/ExpiringItems/List.vue')
const Statistics = () => import('./Pages/Statistics.vue')
const CategoryList = () => import('./Pages/Categories/List.vue')
const CategoryForm = () => import('./Pages/Categories/Form.vue')
const CategoryShow = () => import('./Pages/Categories/Show.vue')
const MediaList = () => import('./Pages/Media/List.vue')

const routes = [
    {
        path: '/',
        name: 'Home',
        component: Home
    },
    {
        path: '/login',
        name: 'Login',
        component: Login
    },
    {
        path: '/register',
        name: 'Register',
        component: Register
    },
    {
        path: '/products',
        name: 'ProductList',
        component: ProductList
    },
    {
        path: '/products/create',
        name: 'ProductCreate',
        component: ProductForm
    },
    {
        path: '/products/:id',
        name: 'ProductView',
        component: ProductShow
    },
    {
        path: '/products/:id/edit',
        name: 'ProductEdit',
        component: ProductForm
    },
    {
        path: '/items',
        name: 'ItemList',
        component: ItemList
    },
    {
        path: '/items/create',
        name: 'ItemCreate',
        component: ItemCreate
    },
    {
        path: '/items/:id',
        name: 'ItemView',
        component: ItemShow
    },
    {
        path: '/items/:id/edit',
        name: 'ItemEdit',
        component: ItemEdit
    },
    {
        path: '/discarded',
        name: 'DiscardedItems',
        component: DiscardedItemList
    },
    {
        path: '/expiring-items',
        name: 'ExpiringItems',
        component: ExpiringItemList
    },
    {
        path: '/statistics',
        name: 'Statistics',
        component: Statistics
    },
    {
        path: '/categories',
        name: 'CategoryList',
        component: CategoryList
    },
    {
        path: '/categories/create',
        name: 'CategoryCreate',
        component: CategoryForm
    },
    {
        path: '/categories/:id',
        name: 'CategoryView',
        component: CategoryShow
    },
    {
        path: '/categories/:id/edit',
        name: 'CategoryEdit',
        component: CategoryForm
    },
    {
        path: '/media',
        name: 'MediaList',
        component: MediaList
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'NotFound',
        component: NotFound
    }
]

export default createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        return { left: 0, top: 0 }
    }
})
