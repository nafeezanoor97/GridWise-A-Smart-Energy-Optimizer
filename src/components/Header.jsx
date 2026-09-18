function Header() {
  return (
    <header className="header">
      <div className="header-content">

        {/* Logo */}
        <div className="logo-area">

          <div className="logo">
            G
          </div>

          <div className="logo-text">
            <h2>GridPilot</h2>
            <p>Smart Energy Scheduling</p>
          </div>

        </div>

        {/* System Status */}
        <div className="system-status">
          <span></span>
          System Online
        </div>

      </div>
    </header>
  );
}

export default Header;