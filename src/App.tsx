import { FormEvent, ReactNode, useEffect, useMemo, useState } from "react";

type Category = "Ficción" | "No ficción" | "Infantil";
type View =
  | "home"
  | "catalog"
  | "detail"
  | "cart"
  | "checkout"
  | "confirmation"
  | "orders"
  | "login"
  | "register"
  | "admin";

const VIEW_PATHS: Record<View, string> = {
  home: "inicio",
  login: "login",
  register: "registro",
  catalog: "catalogo",
  detail: "libro",
  cart: "carrito",
  checkout: "checkout",
  confirmation: "confirmacion",
  orders: "pedidos",
  admin: "administracion",
};

function routeFromHash() {
  const [path, parameter] = window.location.hash.replace(/^#\/?/, "").split("/");
  const view = (Object.entries(VIEW_PATHS).find(([, route]) => route === path)?.[0] ??
    "home") as View;
  return { view, productId: view === "detail" ? Number(parameter) || 1 : undefined };
}
type Product = {
  id: number;
  title: string;
  author: string;
  description: string;
  price: number;
  stock: number;
  category: Category;
  rating: number;
  colors: [string, string];
  mark: string;
};

const INITIAL_PRODUCTS: Product[] = [
  {
    id: 1,
    title: "La ciudad de las sombras",
    author: "Clara Montes",
    description:
      "Una cartógrafa encuentra un mapa imposible que conduce a los secretos enterrados bajo una ciudad que nunca duerme.",
    price: 19.9,
    stock: 12,
    category: "Ficción",
    rating: 4.8,
    colors: ["#0d223c", "#d89d4a"],
    mark: "LAS SOMBRAS",
  },
  {
    id: 2,
    title: "El último verano",
    author: "Martín Salas",
    description:
      "Cuatro amigos vuelven al pueblo costero donde crecieron para enfrentarse a una promesa que dejaron sin cumplir.",
    price: 17.5,
    stock: 8,
    category: "Ficción",
    rating: 4.6,
    colors: ["#dc6b51", "#f5d39d"],
    mark: "VERANO",
  },
  {
    id: 3,
    title: "Atlas de lo invisible",
    author: "Nora Vidal",
    description:
      "Un viaje fascinante por las fuerzas, sistemas y conexiones que moldean nuestra vida sin que podamos verlas.",
    price: 24.9,
    stock: 15,
    category: "No ficción",
    rating: 4.9,
    colors: ["#e8e1d5", "#214f60"],
    mark: "ATLAS",
  },
  {
    id: 4,
    title: "Pensar despacio",
    author: "Daniel Robles",
    description:
      "Herramientas claras para tomar mejores decisiones, reducir el ruido mental y construir una atención más consciente.",
    price: 21,
    stock: 5,
    category: "No ficción",
    rating: 4.7,
    colors: ["#eff0e8", "#e14e3c"],
    mark: "PENSAR",
  },
  {
    id: 5,
    title: "El jardín de Luna",
    author: "Elena Prado",
    description:
      "Luna descubre que las plantas de su abuela hablan al caer la noche y necesitan su ayuda antes del amanecer.",
    price: 14.95,
    stock: 20,
    category: "Infantil",
    rating: 4.9,
    colors: ["#80549a", "#f1c65a"],
    mark: "LUNA",
  },
  {
    id: 6,
    title: "Pequeños exploradores",
    author: "Hugo Soler",
    description:
      "Cincuenta aventuras, experimentos y retos para descubrir la naturaleza en familia, dentro y fuera de casa.",
    price: 16.5,
    stock: 18,
    category: "Infantil",
    rating: 4.8,
    colors: ["#17788a", "#f7b345"],
    mark: "EXPLORA",
  },
  {
    id: 7,
    title: "Cartas desde el norte",
    author: "Inés Beltrán",
    description:
      "Una colección de cartas encontradas en un faro reconstruye una historia de amor separada por el océano.",
    price: 18.9,
    stock: 9,
    category: "Ficción",
    rating: 4.5,
    colors: ["#d9e2e0", "#31596c"],
    mark: "NORTE",
  },
  {
    id: 8,
    title: "Una historia del futuro",
    author: "Álvaro Navas",
    description:
      "Tecnología, sociedad y clima se entrelazan en una mirada rigurosa y optimista hacia las próximas décadas.",
    price: 23.5,
    stock: 11,
    category: "No ficción",
    rating: 4.7,
    colors: ["#191c35", "#34b5b1"],
    mark: "FUTURO",
  },
];

const money = (value: number) => `${value.toFixed(2).replace(".", ",")} €`;

const icons: Record<string, ReactNode> = {
  search: (
    <>
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-4-4" />
    </>
  ),
  cart: (
    <>
      <path d="M3 4h2l2.2 10.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L20 8H7" />
      <circle cx="10" cy="20" r="1" />
      <circle cx="17" cy="20" r="1" />
    </>
  ),
  user: (
    <>
      <circle cx="12" cy="8" r="4" />
      <path d="M4.8 20a7.2 7.2 0 0 1 14.4 0" />
    </>
  ),
  chevron: <path d="m9 18 6-6-6-6" />,
  arrow: (
    <>
      <path d="M5 12h14" />
      <path d="m13 6 6 6-6 6" />
    </>
  ),
  back: (
    <>
      <path d="M19 12H5" />
      <path d="m11 18-6-6 6-6" />
    </>
  ),
  star: <path d="m12 2.5 2.9 5.9 6.5.9-4.7 4.6 1.1 6.5-5.8-3.1-5.8 3.1 1.1-6.5-4.7-4.6 6.5-.9z" />,
  trash: (
    <>
      <path d="M4 7h16M9 7V4h6v3m3 0-1 14H7L6 7m4 4v6m4-6v6" />
    </>
  ),
  check: <path d="m5 12 4 4L19 6" />,
  menu: (
    <>
      <path d="M4 7h16M4 12h16M4 17h16" />
    </>
  ),
  close: (
    <>
      <path d="m6 6 12 12M18 6 6 18" />
    </>
  ),
  edit: (
    <>
      <path d="m4 20 4.2-1 10.7-10.7a2 2 0 0 0-2.8-2.8L5.4 16.2 4 20Z" />
      <path d="m14.5 7.1 2.8 2.8" />
    </>
  ),
  plus: <path d="M12 5v14M5 12h14" />,
  box: (
    <>
      <path d="m4 7 8-4 8 4-8 4-8-4Z" />
      <path d="m4 7 0 10 8 4 8-4V7M12 11v10" />
    </>
  ),
  upload: (
    <>
      <path d="M12 16V4m-4 4 4-4 4 4" />
      <path d="M5 15v5h14v-5" />
    </>
  ),
};

function Icon({
  name,
  size = 20,
  filled = false,
}: {
  name: string;
  size?: number;
  filled?: boolean;
}) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill={filled ? "currentColor" : "none"}
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      {icons[name]}
    </svg>
  );
}

function Logo({ onClick }: { onClick: () => void }) {
  return (
    <button onClick={onClick} className="flex items-center gap-2.5 text-left">
      <span className="relative grid h-9 w-9 place-items-center rounded-lg bg-[#1760DC] text-white shadow-sm">
        <span className="absolute h-4 w-[2px] bg-white/60" />
        <span className="h-5 w-4 rounded-l-sm border-2 border-white border-r-0" />
        <span className="h-5 w-4 rounded-r-sm border-2 border-white border-l-0" />
      </span>
      <span className="text-[21px] font-bold tracking-[-.04em] text-[#142943]">
        Libre<span className="text-[#1760DC]">Shop</span>
      </span>
    </button>
  );
}

function Header({
  navigate,
  cartCount,
  user,
  onLogout,
}: {
  navigate: (v: View) => void;
  cartCount: number;
  user: string | null;
  onLogout: () => void;
}) {
  const [menu, setMenu] = useState(false);
  const go = (v: View) => {
    navigate(v);
    setMenu(false);
  };
  return (
    <header className="sticky top-0 z-40 border-b border-slate-200/80 bg-white/95 backdrop-blur-md">
      <div className="page flex h-[74px] items-center justify-between">
        <Logo onClick={() => go("home")} />
        <nav className="hidden items-center gap-8 text-sm font-semibold text-slate-600 md:flex">
          <button onClick={() => go("home")} className="nav-link">
            Inicio
          </button>
          <button onClick={() => go("catalog")} className="nav-link">
            Categorías
          </button>
          <button onClick={() => (user ? go("orders") : go("login"))} className="nav-link">
            Mis pedidos
          </button>
          <button onClick={() => go("admin")} className="nav-link hidden lg:block">
            Administración
          </button>
        </nav>
        <div className="flex items-center gap-1 sm:gap-3">
          <button
            onClick={() => (user ? go("orders") : go("login"))}
            className="header-action"
            aria-label="Cuenta"
          >
            <Icon name="user" size={21} />
            <span className="hidden lg:inline">{user ? user.split(" ")[0] : "Iniciar sesión"}</span>
          </button>
          {user && (
            <button
              onClick={onLogout}
              className="hidden text-xs font-semibold text-slate-400 hover:text-slate-700 lg:block"
            >
              Salir
            </button>
          )}
          <button
            onClick={() => go("cart")}
            className="relative grid h-10 w-10 place-items-center rounded-full text-[#142943] hover:bg-slate-100"
            aria-label="Carrito"
          >
            <Icon name="cart" size={22} />
            {cartCount > 0 && (
              <span className="absolute right-0 top-0 grid h-5 min-w-5 place-items-center rounded-full bg-[#1760DC] px-1 text-[10px] font-bold text-white">
                {cartCount}
              </span>
            )}
          </button>
          <button
            className="ml-1 grid h-10 w-10 place-items-center rounded-lg md:hidden"
            onClick={() => setMenu(!menu)}
            aria-label="Menú"
          >
            <Icon name={menu ? "close" : "menu"} />
          </button>
        </div>
      </div>
      {menu && (
        <nav className="border-t border-slate-100 bg-white px-5 py-4 md:hidden">
          <button onClick={() => go("home")}>Inicio</button>
          <button onClick={() => go("catalog")}>Catálogo y categorías</button>
          <button onClick={() => (user ? go("orders") : go("login"))}>Mis pedidos</button>
          <button onClick={() => go("admin")}>Administración</button>
        </nav>
      )}
    </header>
  );
}

function Cover({ product, className = "" }: { product: Product; className?: string }) {
  return (
    <div
      className={`book-cover relative isolate overflow-hidden rounded-[3px] shadow-[8px_10px_22px_rgba(15,31,52,.22)] ${className}`}
      style={{ background: `linear-gradient(145deg, ${product.colors[0]}, ${product.colors[1]})` }}
    >
      <div className="absolute inset-y-0 left-0 w-[7%] bg-black/15" />
      <div className="absolute -right-8 top-[14%] h-28 w-28 rounded-full border border-white/35" />
      <div className="absolute -right-3 top-[22%] h-16 w-16 rounded-full border border-white/20" />
      <div className="relative flex h-full flex-col px-[13%] py-[14%] text-white">
        <span className="text-[8px] font-semibold uppercase tracking-[.22em] opacity-80">
          {product.author}
        </span>
        <strong className="mt-auto max-w-[90%] text-[clamp(15px,2vw,24px)] font-bold leading-[.94] tracking-[-.06em]">
          {product.mark}
        </strong>
        <span className="mt-2 h-px w-7 bg-white/70" />
        <span className="mt-2 text-[7px] uppercase tracking-[.2em] opacity-80">
          LibreShop editorial
        </span>
      </div>
    </div>
  );
}

function Rating({ value }: { value: number }) {
  return (
    <div className="flex items-center gap-1 text-amber-400" aria-label={`${value} de 5`}>
      {[1, 2, 3, 4, 5].map((n) => (
        <Icon key={n} name="star" size={14} filled={n <= Math.round(value)} />
      ))}
      <span className="ml-1 text-xs font-semibold text-slate-500">{value}</span>
    </div>
  );
}

function ProductCard({
  product,
  view,
  add,
}: {
  product: Product;
  view: (p: Product) => void;
  add: (id: number) => void;
}) {
  return (
    <article className="group flex h-full flex-col rounded-xl border border-slate-200 bg-white p-4 transition duration-300 hover:-translate-y-1 hover:border-blue-200 hover:shadow-xl hover:shadow-slate-200/70">
      <button
        onClick={() => view(product)}
        className="grid aspect-[4/3] place-items-center rounded-lg bg-[#f3f5f7] p-4"
      >
        <Cover product={product} className="h-full aspect-[.68]" />
      </button>
      <div className="flex flex-1 flex-col pt-4">
        <span className="mb-1 text-[10px] font-bold uppercase tracking-[.14em] text-[#1760DC]">
          {product.category}
        </span>
        <button onClick={() => view(product)} className="text-left">
          <h3 className="line-clamp-1 font-bold tracking-[-.02em] text-[#142943] group-hover:text-[#1760DC]">
            {product.title}
          </h3>
        </button>
        <p className="mt-0.5 text-sm text-slate-500">{product.author}</p>
        <div className="mt-2">
          <Rating value={product.rating} />
        </div>
        <div className="mt-auto flex items-end justify-between gap-3 pt-4">
          <strong className="text-lg text-[#142943]">{money(product.price)}</strong>
          <button
            onClick={() => add(product.id)}
            className="grid h-9 w-9 place-items-center rounded-lg bg-[#1760DC] text-white transition hover:bg-[#114fb9]"
            aria-label={`Añadir ${product.title}`}
          >
            <Icon name="cart" size={17} />
          </button>
        </div>
      </div>
    </article>
  );
}

function SectionHeading({
  eyebrow,
  title,
  action,
}: {
  eyebrow?: string;
  title: string;
  action?: ReactNode;
}) {
  return (
    <div className="mb-7 flex items-end justify-between gap-4">
      <div>
        {eyebrow && (
          <p className="mb-2 text-xs font-bold uppercase tracking-[.18em] text-[#1760DC]">
            {eyebrow}
          </p>
        )}
        <h2 className="title">{title}</h2>
      </div>
      {action}
    </div>
  );
}

function Home({
  products,
  navigate,
  showProduct,
  add,
}: {
  products: Product[];
  navigate: (v: View, category?: Category) => void;
  showProduct: (p: Product) => void;
  add: (id: number) => void;
}) {
  return (
    <>
      <section className="overflow-hidden bg-[#f2f5f8]">
        <div className="page grid min-h-[520px] items-center gap-10 py-12 lg:grid-cols-[1.02fr_.98fr] lg:py-16">
          <div className="relative z-10 max-w-xl">
            <span className="inline-flex items-center gap-2 rounded-full bg-blue-100 px-3 py-1.5 text-xs font-bold uppercase tracking-[.13em] text-[#1760DC]">
              <span className="h-1.5 w-1.5 rounded-full bg-[#1760DC]" /> Historias que dejan huella
            </span>
            <h1 className="mt-6 text-[clamp(42px,6vw,68px)] font-bold leading-[1.02] tracking-[-.055em] text-[#142943]">
              Tu próxima gran historia empieza aquí.
            </h1>
            <p className="mt-5 max-w-lg text-base leading-7 text-slate-600">
              Descubre libros que inspiran, emocionan y transforman. Una selección cuidada para cada
              tipo de lector.
            </p>
            <div className="mt-8 flex flex-wrap gap-3">
              <button onClick={() => navigate("catalog")} className="btn-primary">
                Explorar catálogo <Icon name="arrow" size={17} />
              </button>
              <button onClick={() => navigate("catalog", "Ficción")} className="btn-secondary">
                Ver novedades
              </button>
            </div>
            <div className="mt-9 flex items-center gap-7 text-xs font-semibold text-slate-500">
              <span className="flex items-center gap-2">
                <span className="grid h-6 w-6 place-items-center rounded-full bg-emerald-100 text-emerald-700">
                  <Icon name="check" size={14} />
                </span>{" "}
                Envío gratis +35 €
              </span>
              <span className="flex items-center gap-2">
                <span className="grid h-6 w-6 place-items-center rounded-full bg-emerald-100 text-emerald-700">
                  <Icon name="check" size={14} />
                </span>{" "}
                Compra segura
              </span>
            </div>
          </div>
          <div className="relative min-h-[370px] overflow-hidden rounded-[28px]">
            <img
              src="https://images.unsplash.com/photo-1566131807516-e3b3cd1a89d1?auto=format&fit=crop&w=1200&q=85"
              alt="Interior de una librería contemporánea"
              className="absolute inset-0 h-full w-full object-cover"
            />
            <div className="absolute inset-0 bg-gradient-to-tr from-[#142943]/45 via-transparent to-white/10" />
            <div className="absolute bottom-5 left-5 rounded-xl bg-white/95 p-4 shadow-xl backdrop-blur">
              <p className="text-[10px] font-bold uppercase tracking-[.15em] text-[#1760DC]">
                Libro del mes
              </p>
              <p className="mt-1 font-bold text-[#142943]">Atlas de lo invisible</p>
              <p className="text-xs text-slate-500">Una nueva forma de mirar el mundo</p>
            </div>
          </div>
        </div>
      </section>
      <section className="page py-16 lg:py-20">
        <SectionHeading eyebrow="Encuentra tu lectura" title="Explora por categorías" />
        <div className="grid gap-4 md:grid-cols-3">
          {(
            [
              ["Ficción", "Historias para perderte y encontrarte", "#e9f1ff", "01"],
              ["No ficción", "Ideas para entender mejor el mundo", "#edf5f1", "02"],
              ["Infantil", "Aventuras para pequeños lectores", "#fff4e6", "03"],
            ] as const
          ).map(([cat, text, bg, num]) => (
            <button
              key={cat}
              onClick={() => navigate("catalog", cat)}
              className="group relative overflow-hidden rounded-xl p-6 text-left transition hover:-translate-y-1"
              style={{ background: bg }}
            >
              <span className="absolute right-4 top-1 text-7xl font-black text-[#142943]/5">
                {num}
              </span>
              <span className="grid h-10 w-10 place-items-center rounded-lg bg-white text-[#1760DC] shadow-sm">
                <Icon name="box" />
              </span>
              <h3 className="mt-8 text-xl font-bold text-[#142943]">{cat}</h3>
              <p className="mt-1 text-sm text-slate-500">{text}</p>
              <span className="mt-5 flex items-center gap-1 text-sm font-bold text-[#1760DC]">
                Explorar <Icon name="chevron" size={15} />
              </span>
            </button>
          ))}
        </div>
      </section>
      <section className="bg-[#f7f8fa] py-16 lg:py-20">
        <div className="page">
          <SectionHeading
            eyebrow="Selección LibreShop"
            title="Libros destacados"
            action={
              <button onClick={() => navigate("catalog")} className="link-button">
                Ver todos <Icon name="arrow" size={16} />
              </button>
            }
          />
          <div className="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
            {products.slice(0, 4).map((p) => (
              <ProductCard key={p.id} product={p} view={showProduct} add={add} />
            ))}
          </div>
        </div>
      </section>
    </>
  );
}

function Catalog({
  products,
  initialCategory,
  showProduct,
  add,
}: {
  products: Product[];
  initialCategory: Category | "Todos";
  showProduct: (p: Product) => void;
  add: (id: number) => void;
}) {
  const [category, setCategory] = useState<Category | "Todos">(initialCategory);
  const [query, setQuery] = useState("");
  const filtered = products.filter(
    (p) =>
      (category === "Todos" || p.category === category) &&
      `${p.title} ${p.author}`.toLowerCase().includes(query.toLowerCase()),
  );
  return (
    <main className="page py-10 lg:py-14">
      <div className="mb-9 max-w-2xl">
        <p className="eyebrow">Nuestra colección</p>
        <h1 className="page-title">Catálogo de libros</h1>
        <p className="mt-3 text-slate-500">
          Explora nuestra selección y encuentra la historia que estabas buscando.
        </p>
      </div>
      <div className="mb-8 flex flex-col justify-between gap-4 border-y border-slate-200 py-5 md:flex-row md:items-center">
        <div className="flex flex-wrap gap-2">
          {(["Todos", "Ficción", "No ficción", "Infantil"] as const).map((c) => (
            <button
              key={c}
              onClick={() => setCategory(c)}
              className={`filter-pill ${category === c ? "active" : ""}`}
            >
              {c}
            </button>
          ))}
        </div>
        <label className="relative block w-full md:w-80">
          <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
            <Icon name="search" size={18} />
          </span>
          <input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Buscar por título o autor..."
            className="input pl-10"
          />
        </label>
      </div>
      <div className="mb-5 flex justify-between text-sm text-slate-500">
        <span>
          <strong className="text-[#142943]">{filtered.length}</strong> libros encontrados
        </span>
        <span>Orden: Recomendados</span>
      </div>
      {filtered.length ? (
        <div className="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
          {filtered.map((p) => (
            <ProductCard key={p.id} product={p} view={showProduct} add={add} />
          ))}
        </div>
      ) : (
        <div className="rounded-xl bg-slate-50 py-20 text-center text-slate-500">
          No encontramos libros con esos criterios.
        </div>
      )}
    </main>
  );
}

function Detail({
  product,
  add,
  back,
}: {
  product: Product;
  add: (id: number, qty: number) => void;
  back: () => void;
}) {
  const [qty, setQty] = useState(1);
  return (
    <main className="page py-8 lg:py-14">
      <button
        onClick={back}
        className="mb-8 flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-[#1760DC]"
      >
        <Icon name="back" size={17} /> Volver al catálogo
      </button>
      <div className="grid gap-10 lg:grid-cols-[.85fr_1.15fr] lg:gap-20">
        <div className="grid min-h-[460px] place-items-center rounded-2xl bg-[#f2f4f6] p-10">
          <Cover product={product} className="h-[400px] max-h-[60vh] aspect-[.68]" />
        </div>
        <div className="flex flex-col justify-center">
          <p className="eyebrow">{product.category}</p>
          <h1 className="mt-2 text-[clamp(34px,5vw,52px)] font-bold leading-[1.06] tracking-[-.045em] text-[#142943]">
            {product.title}
          </h1>
          <p className="mt-3 text-lg text-slate-500">
            de <span className="font-semibold text-slate-700">{product.author}</span>
          </p>
          <div className="mt-5 flex items-center gap-3">
            <Rating value={product.rating} />
            <span className="text-xs text-slate-400">128 valoraciones</span>
          </div>
          <div className="my-7 h-px bg-slate-200" />
          <p className="leading-7 text-slate-600">{product.description}</p>
          <p className="mt-5 text-sm leading-6 text-slate-500">
            Una edición cuidada, impresa en papel de alta calidad y pensada para acompañarte durante
            muchas lecturas.
          </p>
          <div className="mt-8 flex items-center justify-between">
            <strong className="text-3xl tracking-[-.03em] text-[#142943]">
              {money(product.price)}
            </strong>
            <span className="flex items-center gap-2 text-sm font-semibold text-emerald-700">
              <span className="h-2 w-2 rounded-full bg-emerald-500" /> En stock · {product.stock}{" "}
              unidades
            </span>
          </div>
          <div className="mt-6 flex gap-3">
            <div className="flex h-12 items-center rounded-lg border border-slate-300 bg-white">
              <button onClick={() => setQty(Math.max(1, qty - 1))} className="h-full px-4 text-xl">
                −
              </button>
              <span className="w-8 text-center font-semibold">{qty}</span>
              <button
                onClick={() => setQty(Math.min(product.stock, qty + 1))}
                className="h-full px-4 text-xl"
              >
                +
              </button>
            </div>
            <button
              onClick={() => add(product.id, qty)}
              className="btn-primary flex-1 justify-center"
            >
              <Icon name="cart" size={19} /> Añadir al carrito
            </button>
          </div>
          <div className="mt-6 grid grid-cols-2 gap-4 rounded-xl bg-slate-50 p-4 text-xs text-slate-500">
            <span>
              <strong className="block text-slate-700">Envío 24–48 h</strong>Gratis desde 35 €
            </span>
            <span>
              <strong className="block text-slate-700">Devolución sencilla</strong>Hasta 30 días
            </span>
          </div>
        </div>
      </div>
    </main>
  );
}

function Cart({
  products,
  cart,
  setQty,
  remove,
  navigate,
}: {
  products: Product[];
  cart: Record<number, number>;
  setQty: (id: number, q: number) => void;
  remove: (id: number) => void;
  navigate: (v: View) => void;
}) {
  const items = products.filter((p) => cart[p.id]);
  const total = items.reduce((sum, p) => sum + p.price * cart[p.id], 0);
  return (
    <main className="page py-10 lg:py-14">
      <p className="eyebrow">Tu selección</p>
      <h1 className="page-title">Carrito de compra</h1>
      {!items.length ? (
        <div className="mt-10 rounded-2xl border border-slate-200 py-20 text-center">
          <span className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-blue-50 text-[#1760DC]">
            <Icon name="cart" size={26} />
          </span>
          <h2 className="mt-5 text-xl font-bold text-[#142943]">Tu carrito está vacío</h2>
          <p className="mt-2 text-sm text-slate-500">
            Hay muchas historias esperando a ser descubiertas.
          </p>
          <button onClick={() => navigate("catalog")} className="btn-primary mt-6">
            Explorar catálogo
          </button>
        </div>
      ) : (
        <div className="mt-8 grid items-start gap-8 lg:grid-cols-[1fr_340px]">
          <div className="overflow-hidden rounded-xl border border-slate-200">
            <div className="hidden grid-cols-[1fr_110px_120px_100px_40px] gap-4 bg-slate-50 px-5 py-3 text-[10px] font-bold uppercase tracking-[.12em] text-slate-500 md:grid">
              <span>Producto</span>
              <span>Precio</span>
              <span>Cantidad</span>
              <span>Subtotal</span>
              <span />
            </div>
            {items.map((p) => (
              <div
                key={p.id}
                className="grid grid-cols-[70px_1fr_auto] items-center gap-4 border-t border-slate-100 p-4 first:border-0 md:grid-cols-[1fr_110px_120px_100px_40px]"
              >
                <div className="flex items-center gap-4">
                  <Cover product={p} className="h-20 aspect-[.68] shrink-0" />
                  <div>
                    <strong className="block text-sm text-[#142943]">{p.title}</strong>
                    <span className="text-xs text-slate-500">{p.author}</span>
                  </div>
                </div>
                <span className="hidden text-sm font-semibold md:block">{money(p.price)}</span>
                <div className="flex h-9 items-center rounded-md border border-slate-300">
                  <button onClick={() => setQty(p.id, cart[p.id] - 1)} className="px-2.5">
                    −
                  </button>
                  <span className="w-6 text-center text-sm font-semibold">{cart[p.id]}</span>
                  <button onClick={() => setQty(p.id, cart[p.id] + 1)} className="px-2.5">
                    +
                  </button>
                </div>
                <strong className="hidden text-sm md:block">{money(p.price * cart[p.id])}</strong>
                <button
                  onClick={() => remove(p.id)}
                  className="text-slate-400 hover:text-red-500"
                  aria-label="Eliminar"
                >
                  <Icon name="trash" size={18} />
                </button>
              </div>
            ))}
          </div>
          <aside className="rounded-xl bg-[#142943] p-6 text-white">
            <h2 className="text-lg font-bold">Resumen del pedido</h2>
            <div className="mt-6 space-y-3 border-b border-white/15 pb-5 text-sm text-slate-300">
              <p className="flex justify-between">
                <span>Subtotal</span>
                <span>{money(total)}</span>
              </p>
              <p className="flex justify-between">
                <span>Envío</span>
                <span className="text-emerald-300">{total >= 35 ? "Gratis" : "3,95 €"}</span>
              </p>
            </div>
            <p className="flex justify-between py-5 text-lg font-bold">
              <span>Total</span>
              <span>{money(total + (total >= 35 ? 0 : 3.95))}</span>
            </p>
            <button
              onClick={() => navigate("checkout")}
              className="flex w-full items-center justify-center gap-2 rounded-lg bg-[#1760DC] px-5 py-3 text-sm font-bold hover:bg-blue-500"
            >
              Realizar pedido <Icon name="arrow" size={16} />
            </button>
            <button
              onClick={() => navigate("catalog")}
              className="mt-3 w-full py-2 text-sm font-semibold text-slate-300 hover:text-white"
            >
              Seguir comprando
            </button>
          </aside>
        </div>
      )}
    </main>
  );
}

function Auth({
  mode,
  navigate,
  login,
}: {
  mode: "login" | "register";
  navigate: (v: View) => void;
  login: (name: string) => void;
}) {
  const [errors, setErrors] = useState<string[]>([]);
  const submit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const data = new FormData(e.currentTarget);
    const next: string[] = [];
    if (!String(data.get("email")).includes("@"))
      next.push("Introduce un correo electrónico válido.");
    if (String(data.get("password")).length < 6)
      next.push("La contraseña debe tener al menos 6 caracteres.");
    if (mode === "register" && data.get("password") !== data.get("confirm"))
      next.push("Las contraseñas no coinciden.");
    setErrors(next);
    if (!next.length) login(mode === "register" ? String(data.get("name")) : "Marina");
  };
  return (
    <main className="grid min-h-[calc(100vh-74px)] lg:grid-cols-2">
      <div className="hidden bg-[#142943] p-12 text-white lg:flex lg:flex-col lg:justify-between">
        <Logo onClick={() => navigate("home")} />
        <div className="mx-auto max-w-md">
          <span className="text-6xl font-serif text-blue-400">“</span>
          <blockquote className="text-3xl font-semibold leading-tight tracking-[-.03em]">
            Un lector vive mil vidas antes de morir. El que nunca lee vive solo una.
          </blockquote>
          <p className="mt-5 text-sm text-slate-400">— George R. R. Martin</p>
        </div>
        <p className="text-xs text-slate-500">© 2025 LibreShop · Tu librería, estés donde estés.</p>
      </div>
      <div className="flex items-center justify-center bg-white p-6 py-16">
        <div className="w-full max-w-md">
          <div className="mb-10 lg:hidden">
            <Logo onClick={() => navigate("home")} />
          </div>
          <p className="eyebrow">
            {mode === "login" ? "Bienvenido de nuevo" : "Únete a LibreShop"}
          </p>
          <h1 className="mt-2 text-4xl font-bold tracking-[-.04em] text-[#142943]">
            {mode === "login" ? "Inicia sesión" : "Crea tu cuenta"}
          </h1>
          <p className="mt-3 text-sm text-slate-500">
            {mode === "login"
              ? "Accede a tus pedidos y continúa descubriendo historias."
              : "Guarda tus favoritos y consulta todos tus pedidos."}
          </p>
          {errors.length > 0 && (
            <div className="mt-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
              {errors.map((e) => (
                <p key={e}>{e}</p>
              ))}
            </div>
          )}
          <form className="mt-7 space-y-4" onSubmit={submit}>
            {mode === "register" && (
              <label className="field-label">
                Nombre completo
                <input name="name" className="input mt-2" placeholder="Tu nombre" required />
              </label>
            )}
            <label className="field-label">
              Correo electrónico
              <input
                name="email"
                type="email"
                className="input mt-2"
                placeholder="nombre@ejemplo.com"
              />
            </label>
            <label className="field-label">
              Contraseña
              <input
                name="password"
                type="password"
                className="input mt-2"
                placeholder="Mínimo 6 caracteres"
              />
            </label>
            {mode === "register" && (
              <label className="field-label">
                Confirmar contraseña
                <input
                  name="confirm"
                  type="password"
                  className="input mt-2"
                  placeholder="Repite tu contraseña"
                />
              </label>
            )}
            <button className="btn-primary mt-2 w-full justify-center" type="submit">
              {mode === "login" ? "Entrar" : "Crear cuenta"} <Icon name="arrow" size={17} />
            </button>
          </form>
          <p className="mt-7 text-center text-sm text-slate-500">
            {mode === "login" ? "¿Aún no tienes cuenta?" : "¿Ya tienes una cuenta?"}{" "}
            <button
              onClick={() => navigate(mode === "login" ? "register" : "login")}
              className="font-bold text-[#1760DC]"
            >
              {mode === "login" ? "Regístrate" : "Inicia sesión"}
            </button>
          </p>
          <button
            onClick={() => navigate("catalog")}
            className="mt-8 flex w-full items-center justify-center gap-2 text-xs font-semibold text-slate-400 hover:text-slate-600"
          >
            <Icon name="back" size={15} /> Continuar como visitante
          </button>
        </div>
      </div>
    </main>
  );
}

function Checkout({
  products,
  cart,
  total,
  confirm,
  back,
}: {
  products: Product[];
  cart: Record<number, number>;
  total: number;
  confirm: () => void;
  back: () => void;
}) {
  const [payment, setPayment] = useState<"card" | "paypal">("card");
  return (
    <main className="page py-10 lg:py-14">
      <button
        onClick={back}
        className="mb-5 flex items-center gap-2 text-sm font-semibold text-slate-500"
      >
        <Icon name="back" size={16} /> Volver al carrito
      </button>
      <p className="eyebrow">Último paso</p>
      <h1 className="page-title">Finalizar pedido</h1>
      <form
        onSubmit={(e) => {
          e.preventDefault();
          confirm();
        }}
        className="mt-8 grid items-start gap-8 lg:grid-cols-[1fr_390px]"
      >
        <div className="space-y-6">
          <section className="panel">
            <h2 className="panel-title">
              <span>1</span> Dirección de envío
            </h2>
            <div className="mt-6 grid gap-4 sm:grid-cols-2">
              <label className="field-label sm:col-span-2">
                Dirección
                <input required className="input mt-2" placeholder="Calle, número, piso..." />
              </label>
              <label className="field-label">
                Código postal
                <input required className="input mt-2" placeholder="28001" />
              </label>
              <label className="field-label">
                Ciudad
                <input required className="input mt-2" placeholder="Madrid" />
              </label>
            </div>
          </section>
          <section className="panel">
            <h2 className="panel-title">
              <span>2</span> Método de pago
            </h2>
            <div className="mt-6 grid gap-3 sm:grid-cols-2">
              <button
                type="button"
                onClick={() => setPayment("card")}
                className={`payment ${payment === "card" ? "active" : ""}`}
              >
                <span className="text-xl">▰</span>
                <span>
                  <strong>Tarjeta</strong>
                  <small>Visa, Mastercard</small>
                </span>
                <i />
              </button>
              <button
                type="button"
                onClick={() => setPayment("paypal")}
                className={`payment ${payment === "paypal" ? "active" : ""}`}
              >
                <span className="text-lg font-black italic text-blue-700">Pay</span>
                <span>
                  <strong>PayPal</strong>
                  <small>Pago seguro</small>
                </span>
                <i />
              </button>
            </div>
            {payment === "card" && (
              <div className="mt-5 grid gap-4 sm:grid-cols-2">
                <label className="field-label sm:col-span-2">
                  Número de tarjeta
                  <input required className="input mt-2" placeholder="1234 5678 9012 3456" />
                </label>
                <label className="field-label">
                  Caducidad
                  <input required className="input mt-2" placeholder="MM/AA" />
                </label>
                <label className="field-label">
                  CVV
                  <input required className="input mt-2" placeholder="123" />
                </label>
              </div>
            )}
          </section>
        </div>
        <aside className="panel lg:sticky lg:top-24">
          <h2 className="text-lg font-bold text-[#142943]">Resumen</h2>
          <div className="mt-5 space-y-4">
            {products
              .filter((p) => cart[p.id])
              .map((p) => (
                <div key={p.id} className="flex gap-3">
                  <Cover product={p} className="h-16 aspect-[.68] shrink-0" />
                  <div className="min-w-0 flex-1">
                    <strong className="block truncate text-sm text-[#142943]">{p.title}</strong>
                    <span className="text-xs text-slate-500">Cantidad: {cart[p.id]}</span>
                  </div>
                  <strong className="text-sm">{money(p.price * cart[p.id])}</strong>
                </div>
              ))}
          </div>
          <div className="mt-6 border-t border-slate-200 pt-5">
            <p className="flex justify-between text-sm text-slate-500">
              <span>Envío</span>
              <span>Gratis</span>
            </p>
            <p className="mt-3 flex justify-between text-xl font-bold text-[#142943]">
              <span>Total</span>
              <span>{money(total)}</span>
            </p>
          </div>
          <button type="submit" className="btn-primary mt-6 w-full justify-center">
            Confirmar pedido <Icon name="check" size={17} />
          </button>
          <p className="mt-3 text-center text-[11px] text-slate-400">
            Pago cifrado y compra 100 % segura
          </p>
        </aside>
      </form>
    </main>
  );
}

function Confirmation({
  products,
  purchased,
  total,
  orders,
}: {
  products: Product[];
  purchased: Record<number, number>;
  total: number;
  orders: () => void;
}) {
  return (
    <main className="page max-w-4xl py-16 text-center lg:py-24">
      <span className="mx-auto grid h-20 w-20 place-items-center rounded-full bg-emerald-100 text-emerald-600">
        <Icon name="check" size={38} />
      </span>
      <p className="eyebrow mt-7">Pedido confirmado</p>
      <h1 className="mt-2 text-4xl font-bold tracking-[-.04em] text-[#142943]">
        ¡Gracias por tu compra!
      </h1>
      <p className="mx-auto mt-3 max-w-lg text-slate-500">
        Hemos recibido tu pedido. Te enviaremos un correo con la información de seguimiento cuando
        salga de nuestro almacén.
      </p>
      <div className="mx-auto mt-9 max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 text-left shadow-sm">
        <div className="flex flex-wrap justify-between gap-5 border-b border-slate-200 pb-5">
          <div>
            <span className="meta">Número de pedido</span>
            <strong className="block text-[#142943]">#LS-2025-0842</strong>
          </div>
          <div>
            <span className="meta">Fecha</span>
            <strong className="block text-[#142943]">18 de junio de 2025</strong>
          </div>
          <div>
            <span className="meta">Estado</span>
            <strong className="block text-amber-600">Pendiente</strong>
          </div>
        </div>
        <div className="py-3">
          {products
            .filter((p) => purchased[p.id])
            .map((p) => (
              <div key={p.id} className="flex items-center gap-3 py-2">
                <Cover product={p} className="h-12 aspect-[.68]" />
                <span className="flex-1 text-sm font-semibold text-[#142943]">
                  {p.title}{" "}
                  <small className="font-normal text-slate-400">× {purchased[p.id]}</small>
                </span>
                <strong className="text-sm">{money(p.price * purchased[p.id])}</strong>
              </div>
            ))}
        </div>
        <p className="flex justify-between border-t border-slate-200 pt-5 text-lg font-bold text-[#142943]">
          <span>Importe total</span>
          <span>{money(total)}</span>
        </p>
      </div>
      <button onClick={orders} className="btn-primary mt-8">
        Ver mis pedidos <Icon name="arrow" size={17} />
      </button>
    </main>
  );
}

function Orders({ details }: { details: () => void }) {
  const orders = [
    ["#LS-2025-0842", "18 jun 2025", "58,30 €", "Pendiente"],
    ["#LS-2025-0619", "02 may 2025", "37,40 €", "Enviado"],
    ["#LS-2025-0291", "14 feb 2025", "24,90 €", "Enviado"],
  ];
  return (
    <main className="page py-10 lg:py-14">
      <p className="eyebrow">Tu biblioteca</p>
      <h1 className="page-title">Mis pedidos</h1>
      <p className="mt-3 text-slate-500">Consulta el estado y los detalles de tus compras.</p>
      <div className="mt-8 overflow-hidden rounded-xl border border-slate-200">
        <div className="hidden grid-cols-5 bg-slate-50 px-6 py-3 text-[10px] font-bold uppercase tracking-[.12em] text-slate-500 md:grid">
          <span>Pedido</span>
          <span>Fecha</span>
          <span>Importe</span>
          <span>Estado</span>
          <span />
        </div>
        {orders.map((o, i) => (
          <div
            key={o[0]}
            className="grid gap-3 border-t border-slate-100 p-5 first:border-0 md:grid-cols-5 md:items-center md:px-6"
          >
            <strong className="text-sm text-[#142943]">{o[0]}</strong>
            <span className="text-sm text-slate-500">{o[1]}</span>
            <strong className="text-sm">{o[2]}</strong>
            <span>
              <em className={`status ${o[3] === "Enviado" ? "sent" : ""}`}>{o[3]}</em>
            </span>
            <button
              onClick={i === 0 ? details : undefined}
              className="flex items-center gap-1 text-sm font-bold text-[#1760DC]"
            >
              Ver detalles <Icon name="chevron" size={15} />
            </button>
          </div>
        ))}
      </div>
    </main>
  );
}

function Admin({
  products,
  setProducts,
}: {
  products: Product[];
  setProducts: (p: Product[]) => void;
}) {
  const [editing, setEditing] = useState<Product | null>(null);
  const save = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!editing) return;
    const d = new FormData(e.currentTarget);
    const saved = {
      ...editing,
      title: String(d.get("title")),
      price: Number(d.get("price")),
      stock: Number(d.get("stock")),
      category: d.get("category") as Category,
    };
    setProducts(
      products.some((p) => p.id === editing.id)
        ? products.map((p) => (p.id === editing.id ? saved : p))
        : [...products, saved],
    );
    setEditing(null);
  };
  return (
    <main className="min-h-[calc(100vh-74px)] bg-slate-50 py-10">
      <div className="page">
        <div className="flex flex-wrap items-end justify-between gap-5">
          <div>
            <p className="eyebrow">Panel de administración</p>
            <h1 className="page-title">Gestión de productos</h1>
            <p className="mt-2 text-sm text-slate-500">
              Administra el catálogo, el precio y el inventario.
            </p>
          </div>
          <button
            onClick={() =>
              setEditing({
                ...products[0],
                id: Math.max(...products.map((p) => p.id)) + 1,
                title: "Nuevo libro",
              })
            }
            className="btn-primary"
          >
            <Icon name="plus" size={18} /> Añadir producto
          </button>
        </div>
        <div className="mt-8 overflow-x-auto rounded-xl border border-slate-200 bg-white">
          <table className="w-full min-w-[800px] text-left">
            <thead className="bg-[#142943] text-[10px] uppercase tracking-[.12em] text-slate-300">
              <tr>
                <th>Producto</th>
                <th>Categoría</th>
                <th>Precio</th>
                <th>Stock</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              {products.map((p) => (
                <tr key={p.id} className="border-t border-slate-100">
                  <td>
                    <div className="flex items-center gap-3">
                      <Cover product={p} className="h-14 aspect-[.68]" />
                      <span>
                        <strong className="block text-sm text-[#142943]">{p.title}</strong>
                        <small className="text-slate-400">{p.author}</small>
                      </span>
                    </div>
                  </td>
                  <td>
                    <span className="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                      {p.category}
                    </span>
                  </td>
                  <td className="font-semibold">{money(p.price)}</td>
                  <td>{p.stock}</td>
                  <td>
                    <span className="flex items-center gap-2 text-xs font-semibold text-emerald-700">
                      <i className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                      Activo
                    </span>
                  </td>
                  <td>
                    <div className="flex gap-2">
                      <button onClick={() => setEditing(p)} className="admin-action">
                        <Icon name="edit" size={16} />
                      </button>
                      <button
                        onClick={() => setProducts(products.filter((x) => x.id !== p.id))}
                        className="admin-action hover:text-red-500"
                      >
                        <Icon name="trash" size={16} />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {editing && (
          <div className="fixed inset-0 z-50 grid place-items-center bg-[#071423]/60 p-4 backdrop-blur-sm">
            <form onSubmit={save} className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
              <div className="flex items-center justify-between">
                <h2 className="text-xl font-bold text-[#142943]">
                  {products.some((p) => p.id === editing.id)
                    ? "Editar producto"
                    : "Añadir producto"}
                </h2>
                <button type="button" onClick={() => setEditing(null)}>
                  <Icon name="close" />
                </button>
              </div>
              <div className="mt-6 grid gap-4 sm:grid-cols-2">
                <label className="field-label sm:col-span-2">
                  Título
                  <input name="title" defaultValue={editing.title} className="input mt-2" />
                </label>
                <label className="field-label">
                  Precio
                  <input
                    name="price"
                    type="number"
                    step=".01"
                    defaultValue={editing.price}
                    className="input mt-2"
                  />
                </label>
                <label className="field-label">
                  Stock
                  <input
                    name="stock"
                    type="number"
                    defaultValue={editing.stock}
                    className="input mt-2"
                  />
                </label>
                <label className="field-label sm:col-span-2">
                  Categoría
                  <select name="category" defaultValue={editing.category} className="input mt-2">
                    <option>Ficción</option>
                    <option>No ficción</option>
                    <option>Infantil</option>
                  </select>
                </label>
                <label className="field-label sm:col-span-2">
                  Portada
                  <button
                    type="button"
                    className="mt-2 flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-slate-300 py-7 text-sm text-slate-500"
                  >
                    <Icon name="upload" /> Subir imagen de portada
                  </button>
                </label>
              </div>
              <div className="mt-6 flex justify-end gap-3">
                <button type="button" onClick={() => setEditing(null)} className="btn-secondary">
                  Cancelar
                </button>
                <button className="btn-primary">Guardar cambios</button>
              </div>
            </form>
          </div>
        )}
      </div>
    </main>
  );
}

function Footer({ navigate }: { navigate: (v: View) => void }) {
  return (
    <footer className="bg-[#0e2036] py-12 text-slate-400">
      <div className="page grid gap-10 sm:grid-cols-2 lg:grid-cols-[1.3fr_.8fr_.8fr_1.2fr]">
        <div>
          <Logo onClick={() => navigate("home")} />
          <p className="mt-4 max-w-xs text-sm leading-6">
            Historias que llegan hasta ti. Descubre, lee y comparte el placer de los libros.
          </p>
        </div>
        <div>
          <h3 className="text-sm font-bold text-white">LibreShop</h3>
          <div className="mt-4 flex flex-col items-start gap-2 text-sm">
            <button onClick={() => navigate("catalog")}>Catálogo</button>
            <button onClick={() => navigate("orders")}>Mis pedidos</button>
            <button onClick={() => navigate("admin")}>Administración</button>
          </div>
        </div>
        <div>
          <h3 className="text-sm font-bold text-white">Atención al lector</h3>
          <p className="mt-4 text-sm">hola@libreshop.es</p>
          <p className="mt-2 text-sm">L–V, 9:00–18:00</p>
        </div>
        <div>
          <h3 className="text-sm font-bold text-white">Mapa del prototipo</h3>
          <div className="mt-4 grid grid-cols-2 gap-x-5 gap-y-2 text-sm">
            <button onClick={() => navigate("login")}>Login</button>
            <button onClick={() => navigate("register")}>Registro</button>
            <button onClick={() => navigate("detail")}>Detalle</button>
            <button onClick={() => navigate("cart")}>Carrito</button>
            <button onClick={() => navigate("checkout")}>Checkout</button>
            <button onClick={() => navigate("confirmation")}>Confirmación</button>
          </div>
        </div>
      </div>
      <div className="page mt-10 border-t border-white/10 pt-5 text-xs">
        © 2025 LibreShop · Proyecto académico DAW
      </div>
    </footer>
  );
}

export default function App() {
  const initialRoute = routeFromHash();
  const [view, setView] = useState<View>(initialRoute.view);
  const [products, setProducts] = useState(INITIAL_PRODUCTS);
  const [selected, setSelected] = useState<Product>(
    INITIAL_PRODUCTS.find((product) => product.id === initialRoute.productId) ??
      INITIAL_PRODUCTS[0],
  );
  const [cart, setCart] = useState<Record<number, number>>({ 2: 1 });
  const [purchased, setPurchased] = useState<Record<number, number>>({ 1: 1, 3: 1 });
  const [purchasedTotal, setPurchasedTotal] = useState(
    INITIAL_PRODUCTS[0].price + INITIAL_PRODUCTS[2].price,
  );
  const [user, setUser] = useState<string | null>(null);
  const [category, setCategory] = useState<Category | "Todos">("Todos");
  const cartCount = Object.values(cart).reduce((a, b) => a + b, 0);
  const total = useMemo(
    () => products.reduce((s, p) => s + (cart[p.id] || 0) * p.price, 0),
    [cart, products],
  );
  useEffect(() => {
    if (!window.location.hash) {
      window.history.replaceState(null, "", `#/${VIEW_PATHS.home}`);
    }
    const syncRoute = () => {
      const route = routeFromHash();
      setView(route.view);
      if (route.productId) {
        setSelected(
          (current) => products.find((product) => product.id === route.productId) ?? current,
        );
      }
      window.scrollTo({ top: 0 });
    };
    window.addEventListener("hashchange", syncRoute);
    return () => window.removeEventListener("hashchange", syncRoute);
  }, [products]);

  const navigate = (v: View, cat?: Category, productId?: number) => {
    if (cat) setCategory(cat);
    setView(v);
    const suffix = v === "detail" ? `/${productId ?? selected.id}` : "";
    const nextHash = `#/${VIEW_PATHS[v]}${suffix}`;
    if (window.location.hash !== nextHash) window.location.hash = nextHash;
    window.scrollTo({ top: 0, behavior: "smooth" });
  };
  const showProduct = (p: Product) => {
    setSelected(p);
    navigate("detail", undefined, p.id);
  };
  const add = (id: number, qty = 1) => {
    setCart((c) => ({ ...c, [id]: (c[id] || 0) + qty }));
  };
  const setQty = (id: number, qty: number) =>
    setCart((c) => {
      const n = { ...c };
      if (qty <= 0) delete n[id];
      else n[id] = qty;
      return n;
    });
  const login = (name: string) => {
    setUser(name || "Lector");
    navigate("home");
  };
  const confirm = () => {
    setPurchased(cart);
    setPurchasedTotal(total);
    setCart({});
    navigate("confirmation");
  };
  const chrome = !["login", "register"].includes(view);
  return (
    <div className="min-h-screen bg-white text-slate-700">
      {chrome && (
        <Header
          navigate={navigate}
          cartCount={cartCount}
          user={user}
          onLogout={() => setUser(null)}
        />
      )}
      {view === "home" && (
        <Home products={products} navigate={navigate} showProduct={showProduct} add={add} />
      )}
      {view === "catalog" && (
        <Catalog
          key={category}
          products={products}
          initialCategory={category}
          showProduct={showProduct}
          add={add}
        />
      )}
      {view === "detail" && (
        <Detail product={selected} add={add} back={() => navigate("catalog")} />
      )}
      {view === "cart" && (
        <Cart
          products={products}
          cart={cart}
          setQty={setQty}
          remove={(id) => setQty(id, 0)}
          navigate={navigate}
        />
      )}
      {view === "checkout" &&
        (cartCount ? (
          <Checkout
            products={products}
            cart={cart}
            total={total}
            confirm={confirm}
            back={() => navigate("cart")}
          />
        ) : (
          <Cart
            products={products}
            cart={cart}
            setQty={setQty}
            remove={(id) => setQty(id, 0)}
            navigate={navigate}
          />
        ))}
      {view === "confirmation" && (
        <Confirmation
          products={products}
          purchased={purchased}
          total={purchasedTotal}
          orders={() => {
            setUser(user || "Marina");
            navigate("orders");
          }}
        />
      )}
      {view === "orders" &&
        (user ? (
          <Orders details={() => navigate("confirmation")} />
        ) : (
          <Auth mode="login" navigate={navigate} login={login} />
        ))}
      {view === "login" && <Auth mode="login" navigate={navigate} login={login} />}
      {view === "register" && <Auth mode="register" navigate={navigate} login={login} />}
      {view === "admin" && <Admin products={products} setProducts={setProducts} />}
      {chrome && view !== "admin" && <Footer navigate={navigate} />}
    </div>
  );
}
