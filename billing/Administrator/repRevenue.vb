Public Class repRevenue 

    Private Sub btn1_Click(sender As Object, e As EventArgs) Handles btn1.Click
        frmRevenue.Show()
        frmRevenue.MdiParent = frmControlpanel
        Me.Dispose()
    End Sub

    Private Sub SimpleButton1_Click(sender As Object, e As EventArgs) Handles SimpleButton1.Click
        frmRevenue.Show()
        frmRevenue.MdiParent = frmControlpanel
        Me.Dispose()
    End Sub

    Private Sub repRevenue_Disposed(sender As Object, e As EventArgs) Handles Me.Disposed

    End Sub

    Private Sub repRevenue_FormClosed(sender As Object, e As FormClosedEventArgs) Handles Me.FormClosed
        Me.Dispose()
    End Sub

    Private Sub repRevenue_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        If (myCon.State = ConnectionState.Open) Then
            myCon.close()
        End If
        For value As Integer = 0 To 12
            ' Exit condition if the value is three.
            cbPeriod.Items.Insert(value.ToString, Date.Now.ToString("yyyy") + "0" + value.ToString("00"))
        Next
    End Sub

    Private Sub txtvolume_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtvolume.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtvolume_TextChanged(sender As Object, e As EventArgs) Handles txtvolume.TextChanged

    End Sub
End Class