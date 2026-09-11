package za.co.chimsy.stands.ui

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ExitToApp
import androidx.compose.material.icons.filled.DateRange
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.automirrored.filled.List
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.NavDestination.Companion.hierarchy
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import za.co.chimsy.stands.ui.dashboard.DashboardScreen
import za.co.chimsy.stands.ui.login.LoginScreen
import za.co.chimsy.stands.ui.sales.SalesScreen
import za.co.chimsy.stands.ui.statements.StatementsScreen

private enum class Destination(val route: String, val label: String) {
    Dashboard("dashboard", "Dashboard"),
    Statements("statements", "Statements"),
    Sales("sales", "Sales"),
}

@Composable
fun StandsApp(
    deviceName: String,
    sessionViewModel: SessionViewModel = viewModel(factory = SessionViewModel.Factory),
) {
    val session by sessionViewModel.state.collectAsStateWithLifecycle()
    val user by sessionViewModel.user.collectAsStateWithLifecycle()

    when (session) {
        SessionState.Unknown -> Box(Modifier.fillMaxSize(), Alignment.Center) { CircularProgressIndicator() }
        SessionState.SignedOut -> LoginScreen(deviceName = deviceName)
        SessionState.SignedIn -> SignedInApp(
            branches = user?.branches.orEmpty(),
            subtitle = user?.email,
            onSignOut = sessionViewModel::signOut,
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SignedInApp(
    branches: List<za.co.chimsy.stands.domain.model.Branch>,
    subtitle: String?,
    onSignOut: () -> Unit,
) {
    val navController = rememberNavController()
    val backStackEntry by navController.currentBackStackEntryAsState()
    val current = backStackEntry?.destination

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(current.titleOrDefault()) },
                actions = {
                    IconButton(onClick = onSignOut) {
                        Icon(Icons.AutoMirrored.Filled.ExitToApp, contentDescription = "Sign out")
                    }
                },
            )
        },
        bottomBar = {
            NavigationBar {
                Destination.entries.forEach { destination ->
                    NavigationBarItem(
                        selected = current?.hierarchy?.any { it.route == destination.route } == true,
                        onClick = {
                            navController.navigate(destination.route) {
                                /** Keeps one entry per tab instead of a stack that grows on every tap. */
                                popUpTo(navController.graph.findStartDestination().id) { saveState = true }
                                launchSingleTop = true
                                restoreState = true
                            }
                        },
                        icon = { Icon(destination.icon(), contentDescription = null) },
                        label = { Text(destination.label) },
                    )
                }
            }
        },
    ) { padding ->
        NavHost(
            navController = navController,
            startDestination = Destination.Dashboard.route,
            modifier = Modifier.fillMaxSize().padding(padding),
        ) {
            composable(Destination.Dashboard.route) { DashboardScreen(branches = branches) }
            composable(Destination.Statements.route) { StatementsScreen(branches = branches) }
            composable(Destination.Sales.route) { SalesScreen(branches = branches) }
        }
    }
}

private fun Destination.icon() = when (this) {
    Destination.Dashboard -> Icons.Default.Home
    Destination.Statements -> Icons.Default.DateRange
    Destination.Sales -> Icons.AutoMirrored.Filled.List
}

private fun androidx.navigation.NavDestination?.titleOrDefault(): String =
    Destination.entries.firstOrNull { it.route == this?.route }?.label ?: "Stand Locator"
