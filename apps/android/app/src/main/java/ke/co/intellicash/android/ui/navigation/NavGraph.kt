package ke.co.intellicash.android.ui.navigation

import androidx.compose.runtime.Composable
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import ke.co.intellicash.android.ui.auth.LoginScreen
import ke.co.intellicash.android.ui.dashboard.DashboardScreen
import ke.co.intellicash.android.ui.groups.GroupDetailScreen
import ke.co.intellicash.android.ui.groups.GroupsScreen
import ke.co.intellicash.android.ui.ledger.LedgerScreen
import ke.co.intellicash.android.ui.meetings.MeetingDetailScreen
import ke.co.intellicash.android.ui.notifications.NotificationsScreen

object Routes {
    const val LOGIN = "login"
    const val DASHBOARD = "dashboard"
    const val GROUPS = "groups"
    const val GROUP_DETAIL = "groups/{groupId}"
    const val MEETING_DETAIL = "groups/{groupId}/meetings/{meetingId}"
    const val LEDGER = "groups/{groupId}/ledger"
    const val NOTIFICATIONS = "notifications"
}

@Composable
fun IntelliCashNavHost() {
    val navController = rememberNavController()

    NavHost(navController = navController, startDestination = Routes.LOGIN) {
        composable(Routes.LOGIN) {
            LoginScreen(
                onLoginSuccess = {
                    navController.navigate(Routes.DASHBOARD) {
                        popUpTo(Routes.LOGIN) { inclusive = true }
                    }
                }
            )
        }

        composable(Routes.DASHBOARD) {
            DashboardScreen(
                onNavigateToGroups = { navController.navigate(Routes.GROUPS) },
                onNavigateToNotifications = { navController.navigate(Routes.NOTIFICATIONS) },
                onLogout = {
                    navController.navigate(Routes.LOGIN) {
                        popUpTo(0) { inclusive = true }
                    }
                }
            )
        }

        composable(Routes.GROUPS) {
            GroupsScreen(
                onBack = { navController.popBackStack() },
                onGroupClick = { groupId ->
                    navController.navigate("groups/$groupId")
                }
            )
        }

        composable(
            Routes.GROUP_DETAIL,
            arguments = listOf(navArgument("groupId") { type = NavType.StringType })
        ) {
            GroupDetailScreen(
                onBack = { navController.popBackStack() },
                onMeetingClick = { groupId, meetingId ->
                    navController.navigate("groups/$groupId/meetings/$meetingId")
                },
                onLedgerClick = { groupId ->
                    navController.navigate("groups/$groupId/ledger")
                }
            )
        }

        composable(
            Routes.MEETING_DETAIL,
            arguments = listOf(
                navArgument("groupId") { type = NavType.StringType },
                navArgument("meetingId") { type = NavType.StringType }
            )
        ) {
            MeetingDetailScreen(onBack = { navController.popBackStack() })
        }

        composable(
            Routes.LEDGER,
            arguments = listOf(navArgument("groupId") { type = NavType.StringType })
        ) {
            LedgerScreen(onBack = { navController.popBackStack() })
        }

        composable(Routes.NOTIFICATIONS) {
            NotificationsScreen(onBack = { navController.popBackStack() })
        }
    }
}
